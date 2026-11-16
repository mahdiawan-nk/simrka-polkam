<?php

namespace App\Services\FundRequest;

use App\Models\DepartmentBudget;
use App\Models\FundRequest;
use App\Models\FundRequestApproval;
use App\Models\RkaSetting;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Presenter terpusat untuk Halaman Detail Pengajuan Pemohon dan Cetak PDF Fund Request
 * sehingga seluruh angka, status, rekap rekening, dan riwayat persetujuan selalu konsisten.
 */
class FundRequestDetailService
{
    public function build(FundRequest $record): array
    {
        $record->loadMissing([
            'department',
            'creator',
            'rkaHeader.schedule',
            'items.account',
            'items.rkaItem.indicator',
            'verifications.account',
            'verifications.finalAccount',
            'approvals.user',
        ]);

        $items = $record->items;
        $status = strtolower((string) $record->status);
        $isHeaderRejected = $status === 'rejected';

        $totalRequested = round((float) $items->sum('total_price'), 2);
        $totalApproved = $isHeaderRejected
            ? 0.0
            : round((float) $items->sum(function ($item): float {
                if (in_array($item->verification_status, ['approved', 'partial'], true)) {
                    return (float) ($item->approved_price ?? 0);
                }

                return 0.0;
            }), 2);

        $totalNotApproved = $isHeaderRejected
            ? $totalRequested
            : round((float) $items->sum(function ($item): float {
                if ($item->verification_status === 'rejected') {
                    return (float) $item->total_price;
                }
                if (in_array($item->verification_status, ['approved', 'partial'], true)) {
                    return max(0.0, (float) $item->total_price - (float) ($item->approved_price ?? 0));
                }

                return 0.0;
            }), 2);

        $accountRecaps = $items
            ->groupBy('account_id')
            ->map(function ($group) use ($isHeaderRejected): array {
                $first = $group->first();
                $req = round((float) $group->sum('total_price'), 2);
                $app = $isHeaderRejected
                    ? 0.0
                    : round((float) $group->sum(function ($item): float {
                        if (in_array($item->verification_status, ['approved', 'partial'], true)) {
                            return (float) ($item->approved_price ?? 0);
                        }

                        return 0.0;
                    }), 2);

                return [
                    'account_id' => $first->account_id,
                    'account_code' => $first->account?->code ?? $first->rka_account_code_snapshot ?? '-',
                    'account_name' => $first->account?->name ?? $first->rka_account_name_snapshot ?? '-',
                    'item_count' => $group->count(),
                    'total_requested' => $req,
                    'total_approved' => $app,
                    'selisih' => max(0.0, round($req - $app, 2)),
                ];
            })
            ->values()
            ->all();

        $approvalsByStage = $record->approvals->keyBy('stage');
        $kabakkuApproval = $approvalsByStage->get('kabakku');
        $wadirApproval = $approvalsByStage->get('wadir_bakku');
        $directorApproval = $approvalsByStage->get('director');

        $stageOrder = [
            'draft' => 0,
            'submitted' => 1,
            'verified_kabakku' => 2,
            'verified_wadir_bakku' => 3,
            'approved' => 4,
        ];
        $currentRank = $stageOrder[$status] ?? -1;

        $workflowSteps = [
            [
                'key' => 'draft',
                'label' => 'Draft',
                'state' => $status === 'draft' ? 'current' : 'completed',
                'actor' => $record->creator?->name ?? '-',
                'timestamp' => $record->created_at?->format('d M Y H:i'),
            ],
            [
                'key' => 'submitted',
                'label' => 'Diajukan',
                'state' => match (true) {
                    $status === 'submitted' => 'current',
                    $currentRank > 1 || $kabakkuApproval !== null || $isHeaderRejected => 'completed',
                    default => 'upcoming',
                },
                'actor' => $record->creator?->name ?? '-',
                'timestamp' => $status !== 'draft' ? $record->created_at?->format('d M Y H:i') : null,
            ],
            $this->buildApprovalStep('verified_kabakku', 'Verifikasi Keuangan', $kabakkuApproval, $status === 'verified_kabakku', $currentRank >= 2),
            $this->buildApprovalStep('verified_wadir_bakku', 'Persetujuan Wadir Keuangan', $wadirApproval, $status === 'verified_wadir_bakku', $currentRank >= 3),
            $this->buildApprovalStep('approved', 'Pengesahan Direktur', $directorApproval, $status === 'approved', $currentRank >= 4),
        ];

        $approvalHistory = [
            $this->formatApprovalStage('Bagian Keuangan (Ka BAKKU)', 'kabakku', $kabakkuApproval),
            $this->formatApprovalStage('Wadir Keuangan (Wadir BAKKU)', 'wadir_bakku', $wadirApproval),
            $this->formatApprovalStage('Direktur', 'director', $directorApproval),
        ];

        $financeSummary = [
            'verifier_name' => $kabakkuApproval?->user?->name ?? '-',
            'verified_at' => $kabakkuApproval?->created_at?->format('d M Y H:i'),
            'decision' => $kabakkuApproval ? $this->actionLabel($kabakkuApproval->action) : 'Menunggu Verifikasi',
            'total_approved' => $totalApproved,
            'note' => $kabakkuApproval?->note,
            'adjusted_count' => $items->where('verification_status', 'partial')->count(),
            'rejected_count' => $items->where('verification_status', 'rejected')->count(),
            'pending_count' => $items->whereNull('verification_status')->count(),
        ];

        $activityLogs = $this->buildActivityLogs($record);
        $attachments = $this->buildAttachments($record);

        return [
            'record' => $record,
            'status_label' => $this->statusLabel($status),
            'status_color' => $this->statusColor($status),
            'summary' => [
                'total_requested' => $totalRequested,
                'total_approved' => $totalApproved,
                'total_not_approved' => $totalNotApproved,
            ],
            'account_recaps' => $accountRecaps,
            'workflow_steps' => $workflowSteps,
            'approval_history' => $approvalHistory,
            'finance_summary' => $financeSummary,
            'activity_logs' => $activityLogs,
            'attachments' => $attachments,
        ];
    }

    /**
     * Membangun payload lengkap untuk cetak PDF resmi Fund Request menggunakan
     * konfigurasi aktif "Konfigurasi Form Permohonan Pencairan Dana" dari RKA Settings.
     *
     * @throws RuntimeException ketika konfigurasi Form Permohonan Pencairan Dana tidak tersedia atau ambigu.
     */
    public function buildForPdf(FundRequest $record): array
    {
        $detail = $this->build($record);
        $setting = $this->resolvePdfConfiguration();
        $pdfContext = $this->buildPdfContext($detail['record'], $detail, $setting);

        return array_merge($detail, [
            'detail' => $detail,
            'setting' => $setting,
            'pdf' => $pdfContext,
        ]);
    }

    /**
     * Mengambil konfigurasi RKA Settings khusus Form Permohonan Pencairan Dana.
     *
     * @throws RuntimeException ketika konfigurasi belum ada atau ambigu.
     */
    public function resolvePdfConfiguration(): RkaSetting
    {
        $candidates = RkaSetting::query()
            ->get()
            ->filter(function (RkaSetting $setting): bool {
                $name = strtolower(trim((string) $setting->setting_name));

                if ($name === '' || str_contains($name, 'format rka')) {
                    return false;
                }

                return str_contains($name, 'permohonan pencairan')
                    || str_contains($name, 'pencairan dana');
            })
            ->values();

        if ($candidates->isEmpty()) {
            throw new RuntimeException(
                'Konfigurasi Form Permohonan Pencairan Dana belum tersedia di RKA Settings. Silakan tambahkan konfigurasi tersebut terlebih dahulu.'
            );
        }

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        $exactMatches = $candidates->filter(
            fn (RkaSetting $setting): bool => strtolower(trim((string) $setting->setting_name)) === 'konfigurasi form permohonan pencairan dana'
        )->values();

        if ($exactMatches->count() === 1) {
            return $exactMatches->first();
        }

        throw new RuntimeException(
            'Konfigurasi Form Permohonan Pencairan Dana di RKA Settings bersifat ambigu (ditemukan lebih dari satu konfigurasi yang cocok).'
        );
    }

    private function buildPdfContext(FundRequest $record, array $detail, RkaSetting $setting): array
    {
        $headerConfig = $setting->pdf_header_config ?? [];
        $footerConfig = $setting->pdf_footer_config ?? [];
        $headerType = (string) ($headerConfig['header_type'] ?? 'manual_two_logos');

        $header = [
            'type' => $headerType,
            'full_kop_data_uri' => $headerType === 'full_image'
                ? $this->resolveStorageImageDataUri($headerConfig['full_kop_image'] ?? null)
                : null,
            'left_logo_data_uri' => in_array($headerType, ['manual_two_logos', 'manual_single_logo'], true)
                ? $this->resolveStorageImageDataUri($headerConfig['left_logo'] ?? null)
                : null,
            'right_logo_data_uri' => $headerType === 'manual_two_logos'
                ? $this->resolveStorageImageDataUri($headerConfig['right_logo'] ?? null)
                : null,
            'single_logo_position' => (string) ($headerConfig['single_logo_position'] ?? 'left'),
            'foundation_name' => (string) ($headerConfig['foundation_name'] ?? 'YAYASAN DATUK TABANO'),
            'institution_name' => (string) ($headerConfig['institution_name'] ?? 'POLITEKNIK KAMPAR'),
            'address_line' => (string) ($headerConfig['address_line'] ?? ''),
            'contact_line' => (string) ($headerConfig['contact_line'] ?? ''),
            'show_divider_line' => (bool) ($headerConfig['show_divider_line'] ?? true),
        ];

        $accountIds = $record->items->pluck('account_id')->filter()->unique()->values()->all();
        $fiscalYear = (string) ($record->fiscal_year ?: ($record->rkaHeader?->schedule?->fiscal_year ?? ''));

        $annualBudgets = empty($accountIds)
            ? collect()
            : DepartmentBudget::query()
                ->where('department_id', $record->department_id)
                ->where('fiscal_year', $fiscalYear)
                ->whereIn('account_id', $accountIds)
                ->get()
                ->groupBy('account_id')
                ->map(fn ($rows): float => (float) $rows->sum('allocated_amount'));

        $accountsById = $record->items
            ->pluck('account')
            ->filter()
            ->keyBy('id');

        $sectionCRows = collect($detail['account_recaps'])->map(function (array $recap) use ($annualBudgets, $accountsById): array {
            $account = $accountsById->get($recap['account_id']);
            $paguUnit = round((float) ($annualBudgets->get($recap['account_id'], 0.0)), 2);
            $requested = (float) $recap['total_requested'];
            $isCentralized = (bool) ($account?->is_centralized ?? false);

            $statusText = match (true) {
                $paguUnit <= 0 => 'Belum ada pagu',
                $requested <= $paguUnit => 'Sesuai pagu',
                default => 'Melebihi pagu',
            };

            return [
                'account_id' => $recap['account_id'],
                'code' => $recap['account_code'],
                'name' => $recap['account_name'],
                'is_centralized' => $isCentralized,
                'catatan' => $isCentralized ? ((string) ($account?->managed_by_note ?: 'Pos dikelola terpusat')) : '',
                'total_requested' => $requested,
                'pagu_unit' => $paguUnit,
                'status' => $statusText,
            ];
        })->values()->all();

        $isHeaderRejected = strtolower((string) $record->status) === 'rejected';
        if ($record->verifications->isNotEmpty()) {
            $sectionFRows = $record->verifications->map(function ($verification) use ($isHeaderRejected): array {
                $statusText = $isHeaderRejected ? 'Ditolak' : (string) $verification->status;
                $isApprovedRow = ! $isHeaderRejected && in_array($statusText, ['Disetujui', 'Disetujui Sebagian'], true);

                return [
                    'code' => $verification->account?->code ?? '-',
                    'final_code' => ($verification->final_account_id && $verification->final_account_id !== $verification->account_id)
                        ? ($verification->finalAccount?->code ?? '-')
                        : '',
                    'remaining_budget' => (float) $verification->remaining_budget,
                    'amount_approved' => $isHeaderRejected ? 0.0 : (float) $verification->amount_approved,
                    'status' => $statusText,
                    'register_proof_number' => ($isApprovedRow && filled($verification->register_proof_number))
                        ? (string) $verification->register_proof_number
                        : '',
                ];
            })->values()->all();
        } else {
            $sectionFRows = collect($detail['account_recaps'])->map(fn (array $recap): array => [
                'code' => $recap['account_code'],
                'final_code' => '',
                'remaining_budget' => null,
                'amount_approved' => null,
                'status' => $isHeaderRejected ? 'Ditolak' : 'Menunggu',
                'register_proof_number' => '',
            ])->values()->all();
        }

        $sectionFTotal = $isHeaderRejected
            ? 0.0
            : ($record->verifications->isNotEmpty()
                ? round((float) collect($sectionFRows)->sum(fn (array $row): float => (float) ($row['amount_approved'] ?? 0)), 2)
                : (float) $detail['summary']['total_approved']);

        $signingItems = collect($setting->signing_config ?? [])->keyBy('role');
        $approvalsByStage = $record->approvals->keyBy('stage');

        $pemohonCfg = $signingItems->get('pemohon', []);
        $pemohonLabel = trim((string) ($pemohonCfg['label'] ?? ''));
        if ($pemohonLabel === '' || $pemohonLabel === '-') {
            $pemohonLabel = 'Kepala '.($record->department?->name ?? 'Unit Kerja');
        }

        $signatures = [
            'signing_place' => (string) ($setting->signing_place ?: 'Bangkinang'),
            'signing_date' => $this->formatIndonesianDate($record->request_date),
            'pemohon' => [
                'stage_label' => $this->ensureCommaSuffix((string) ($pemohonCfg['stage_key'] ?? 'Pemohon')),
                'role_title' => $pemohonLabel,
                'name' => $record->responsible_person ?: ($record->creator?->name ?? '-'),
                'status_text' => strtolower((string) $record->status) !== 'draft' ? 'Diajukan' : 'Draft',
                'is_signed' => strtolower((string) $record->status) !== 'draft',
            ],
            'kabakku' => $this->buildSignatureSlot(
                $signingItems->get('keuangan', []),
                'Diverifikasi,',
                'Kabag KKU',
                $approvalsByStage->get('kabakku'),
                'Menunggu Verifikasi'
            ),
            'wadir' => $this->buildSignatureSlot(
                $signingItems->get('wadir_keuangan', []),
                'Disetujui,',
                'Wakil Direktur II',
                $approvalsByStage->get('wadir_bakku'),
                'Menunggu Persetujuan'
            ),
            'direktur' => $this->buildSignatureSlot(
                $signingItems->get('direktur', []),
                'Menyetujui,',
                'Direktur Politeknik Kampar',
                $approvalsByStage->get('director'),
                'Menunggu Pengesahan',
                $setting->director_name,
                $setting->director_nrp
            ),
        ];

        return [
            'header' => $header,
            'footer_text' => (string) ($footerConfig['footer_text'] ?? ''),
            'show_page_number' => (bool) ($footerConfig['show_page_number'] ?? true),
            'request_date_formatted' => $this->formatIndonesianDate($record->request_date),
            'execution_date_formatted' => $record->execution_date ? $this->formatIndonesianDate($record->execution_date) : '-',
            'terbilang' => $this->terbilang((int) round((float) $detail['summary']['total_requested'])),
            'section_c_rows' => $sectionCRows,
            'section_f_rows' => $sectionFRows,
            'section_f_total' => $sectionFTotal,
            'signatures' => $signatures,
            'foundation_head_name' => (string) ($setting->foundation_head_name ?? ''),
        ];
    }

    private function buildSignatureSlot(
        array $config,
        string $defaultStageLabel,
        string $defaultRoleTitle,
        ?FundRequestApproval $approval,
        string $pendingText,
        ?string $fallbackName = null,
        ?string $fallbackNip = null,
    ): array {
        $stageLabel = $this->ensureCommaSuffix((string) ($config['stage_key'] ?? $defaultStageLabel));
        $roleTitle = trim((string) ($config['label'] ?? '')) ?: $defaultRoleTitle;
        $configuredName = trim((string) ($config['manual_name'] ?? '')) ?: ($fallbackName ?? '');
        $configuredNip = trim((string) ($config['manual_nip'] ?? '')) ?: ($fallbackNip ?? '');

        $isApproved = $approval?->action === 'approved';
        $isRejected = $approval?->action === 'rejected';

        $displayName = $configuredName !== '' ? $configuredName : ($approval?->user?->name ?? '-');
        $statusText = match (true) {
            $isApproved => 'Disetujui • '.$approval->created_at?->format('d/m/Y'),
            $isRejected => 'Ditolak • '.$approval->created_at?->format('d/m/Y'),
            default => $pendingText,
        };

        return [
            'stage_label' => $stageLabel,
            'role_title' => $roleTitle,
            'name' => $displayName,
            'nip' => $configuredNip,
            'status_text' => $statusText,
            'is_signed' => $isApproved,
            'is_rejected' => $isRejected,
        ];
    }

    private function ensureCommaSuffix(string $text): string
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return '';
        }

        return str_ends_with($trimmed, ',') ? $trimmed : $trimmed.',';
    }

    private function resolveStorageImageDataUri(?string $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $trimmed = ltrim(trim($path), '/');
        $rawBytes = null;
        $mime = null;

        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($trimmed)) {
                $rawBytes = Storage::disk($disk)->get($trimmed);
                $mime = Storage::disk($disk)->mimeType($trimmed) ?: null;
                break;
            }
        }

        if ($rawBytes === null && file_exists(public_path($trimmed))) {
            $rawBytes = file_get_contents(public_path($trimmed));
            $mime = mime_content_type(public_path($trimmed)) ?: null;
        }

        if ($rawBytes === null || $rawBytes === false) {
            return null;
        }

        $ext = strtolower(pathinfo($trimmed, PATHINFO_EXTENSION));
        $mime ??= match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        return 'data:'.$mime.';base64,'.base64_encode($rawBytes);
    }

    private function formatIndonesianDate(?CarbonInterface $date): string
    {
        if (! $date) {
            return '-';
        }

        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        $monthName = $months[(int) $date->format('n')] ?? $date->format('F');

        return $date->format('j').' '.$monthName.' '.$date->format('Y');
    }

    private function terbilang(int $number): string
    {
        if ($number <= 0) {
            return 'Nol Rupiah';
        }

        return ucwords(trim($this->convertNumberToWords($number))).' Rupiah';
    }

    private function convertNumberToWords(int $number): string
    {
        $words = [
            '',
            'satu',
            'dua',
            'tiga',
            'empat',
            'lima',
            'enam',
            'tujuh',
            'delapan',
            'sembilan',
            'sepuluh',
            'sebelas',
        ];

        if ($number < 12) {
            return $words[$number];
        }
        if ($number < 20) {
            return $this->convertNumberToWords($number - 10).' belas';
        }
        if ($number < 100) {
            return trim($this->convertNumberToWords(intdiv($number, 10)).' puluh '.$this->convertNumberToWords($number % 10));
        }
        if ($number < 200) {
            return trim('seratus '.$this->convertNumberToWords($number - 100));
        }
        if ($number < 1000) {
            return trim($this->convertNumberToWords(intdiv($number, 100)).' ratus '.$this->convertNumberToWords($number % 100));
        }
        if ($number < 2000) {
            return trim('seribu '.$this->convertNumberToWords($number - 1000));
        }
        if ($number < 1000000) {
            return trim($this->convertNumberToWords(intdiv($number, 1000)).' ribu '.$this->convertNumberToWords($number % 1000));
        }
        if ($number < 1000000000) {
            return trim($this->convertNumberToWords(intdiv($number, 1000000)).' juta '.$this->convertNumberToWords($number % 1000000));
        }
        if ($number < 1000000000000) {
            return trim($this->convertNumberToWords(intdiv($number, 1000000000)).' miliar '.$this->convertNumberToWords($number % 1000000000));
        }

        return trim($this->convertNumberToWords(intdiv($number, 1000000000000)).' triliun '.$this->convertNumberToWords($number % 1000000000000));
    }

    private function buildApprovalStep(
        string $key,
        string $label,
        ?FundRequestApproval $approval,
        bool $isCurrent,
        bool $isReached,
    ): array {
        $state = 'upcoming';
        if ($approval?->action === 'rejected') {
            $state = 'rejected';
        } elseif ($approval?->action === 'approved' || $isReached) {
            $state = $isCurrent ? 'current' : 'completed';
        }

        return [
            'key' => $key,
            'label' => $label,
            'state' => $state,
            'actor' => $approval?->user?->name,
            'timestamp' => $approval?->created_at?->format('d M Y H:i'),
        ];
    }

    private function formatApprovalStage(string $roleTitle, string $stage, ?FundRequestApproval $approval): array
    {
        return [
            'stage' => $stage,
            'role_title' => $roleTitle,
            'officer_name' => $approval?->user?->name ?? 'Menunggu Proses',
            'decision' => $approval ? $this->actionLabel($approval->action) : 'Menunggu',
            'action' => $approval?->action ?? 'pending',
            'decided_at' => $approval?->created_at?->format('d M Y H:i'),
            'note' => $approval?->note,
        ];
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function buildActivityLogs(FundRequest $record): array
    {
        $logs = [
            [
                'activity' => 'Pengajuan dibuat'.($record->status !== 'draft' ? ' & dikirim' : ''),
                'actor' => $record->creator?->name ?? '-',
                'time' => $record->created_at?->format('d M Y H:i') ?? '-',
                'description' => "Nomor pengajuan {$record->document_number} dengan {$record->items->count()} item kebutuhan.",
            ],
        ];

        foreach ($record->approvals->sortBy('created_at') as $approval) {
            $stageLabel = match ($approval->stage) {
                'kabakku' => 'Verifikasi Bagian Keuangan',
                'wadir_bakku' => 'Persetujuan Wadir Keuangan',
                'director' => 'Pengesahan Direktur',
                default => ucfirst($approval->stage),
            };
            $logs[] = [
                'activity' => "{$stageLabel} ({$this->actionLabel($approval->action)})",
                'actor' => $approval->user?->name ?? '-',
                'time' => $approval->created_at?->format('d M Y H:i') ?? '-',
                'description' => $approval->note ?: 'Tidak ada catatan tambahan.',
            ];
        }

        return $logs;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildAttachments(FundRequest $record): array
    {
        $raw = $record->attachments ?? [];
        if (! is_array($raw)) {
            return [];
        }

        $result = [];
        foreach (array_values($raw) as $index => $path) {
            if (! is_string($path) || trim($path) === '') {
                continue;
            }
            $ext = strtoupper(pathinfo($path, PATHINFO_EXTENSION) ?: 'FILE');
            $existsPublic = Storage::disk('public')->exists($path);
            $existsLocal = Storage::disk('local')->exists($path);

            $result[] = [
                'index' => $index,
                'name' => basename($path),
                'type' => $ext,
                'uploaded_at' => $record->created_at?->format('d M Y H:i') ?? '-',
                'uploader' => $record->creator?->name ?? '-',
                'available' => $existsPublic || $existsLocal,
                'download_url' => route('fund-requests.attachment', ['record' => $record->id, 'index' => $index]),
            ];
        }

        return $result;
    }

    private function actionLabel(string $action): string
    {
        return match ($action) {
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'revised' => 'Revisi Diminta',
            default => ucfirst($action),
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Draft',
            'submitted' => 'Diajukan',
            'verified_kabakku' => 'Diverifikasi Keuangan',
            'verified_wadir_bakku' => 'Disetujui Wadir Keuangan',
            'approved' => 'Disetujui Final',
            'rejected' => 'Ditolak',
            default => ucfirst($status),
        };
    }

    private function statusColor(string $status): string
    {
        return match ($status) {
            'draft' => 'gray',
            'submitted' => 'warning',
            'verified_kabakku', 'verified_wadir_bakku' => 'info',
            'approved' => 'success',
            'rejected' => 'danger',
            default => 'primary',
        };
    }
}
