<?php

namespace App\Services;

use App\Exports\ApprovedRkaTemplateExport;
use App\Models\Account;
use App\Models\RkaAdjustment;
use App\Models\RkaHeader;
use App\Models\RkaItem;
use App\Models\RkaLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Layanan untuk memvalidasi akses, menyiapkan data, dan mengekspor RKA APPROVED ke format Excel.
 */
class RkaApprovedTemplateService
{
    /**
     * Daftar 6 kelompok belanja resmi sesuai urutan template RKA Politeknik Kampar.
     *
     * @var array<string, string>
     */
    public const SPENDING_GROUPS = [
        '5.1' => '5.1 Belanja Pegawai',
        '5.2' => '5.2 Belanja Barang dan Jasa',
        '5.3' => '5.3 Belanja Modal',
        '5.4' => '5.4 Belanja Hibah',
        '5.5' => '5.5 Belanja Pajak',
        '2.1' => '2.1 Pembayaran Kewajiban Jangka Pendek',
    ];

    public function __construct(
        private readonly RkaActivityLogService $activityLogService,
    ) {}

    /**
     * Memvalidasi hak akses user dan status APPROVED pada RKA Header.
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     */
    public function validateExportAccess(RkaHeader $header, User $user): void
    {
        if (! $user->can('view', $header)) {
            throw new AuthorizationException('Anda tidak memiliki izin untuk mengekspor RKA ini.');
        }

        if (strtoupper((string) $header->status) !== RkaHeader::STATUS_APPROVED) {
            throw new InvalidArgumentException('Hanya RKA dengan status APPROVED yang dapat diekspor.');
        }
    }

    /**
     * Membangun nama file export standar: RKA-APPROVED-{UNIT}-{TAHUN}.xlsx.
     */
    public function buildExportFilename(RkaHeader $header): string
    {
        $header->loadMissing(['department', 'schedule']);

        $unitRaw = $header->department?->name ?? 'UNIT';
        $yearRaw = $header->schedule?->fiscal_year ?? now()->format('Y');

        $unitSlug = Str::upper(Str::slug($unitRaw, '_'));
        $yearSlug = preg_replace('/[^A-Za-z0-9\-_]/', '-', (string) $yearRaw) ?: now()->format('Y');

        return "RKA-APPROVED-{$unitSlug}-{$yearSlug}.xlsx";
    }

    /**
     * Menyiapkan seluruh data terstruktur (read-only) untuk 2 worksheet export.
     *
     * @return array<string, mixed>
     */
    public function prepareWorkbookData(RkaHeader $header): array
    {
        $header->loadMissing([
            'department',
            'schedule',
            'setting',
            'unitHead',
            'reviewer',
        ]);

        $adjustments = RkaAdjustment::query()
            ->where('rka_header_id', $header->id)
            ->with([
                'rkaHeader.department',
                'rkaItem.rkaHeader.department',
                'rkaItem.account',
                'account',
                'destinationUnit',
                'targetUnit',
                'adjustedBy',
            ])
            ->orderBy('section_type')
            ->orderBy('id')
            ->get();

        $sectionA = [];
        $sectionB = [];

        foreach ($adjustments as $adj) {
            $mapped = $this->mapAdjustmentRow($adj, $header);
            if ($adj->section_type === RkaAdjustment::SECTION_A) {
                $sectionA[] = $mapped;
            } else {
                $sectionB[] = $mapped;
            }
        }

        $items = RkaItem::query()
            ->where('rka_header_id', $header->id)
            ->with([
                'indicator',
                'account.parent.parent',
            ])
            ->orderBy('form_type')
            ->orderBy('account_id')
            ->orderBy('id')
            ->get();

        $groupedItems = [];
        foreach (self::SPENDING_GROUPS as $groupCode => $groupLabel) {
            $groupedItems[$groupCode] = [
                'code' => $groupCode,
                'label' => $groupLabel,
                'rows' => [],
                'total_proposed' => 0.0,
                'total_approved' => 0.0,
            ];
        }

        $grandProposed = 0.0;
        $grandApproved = 0.0;

        foreach ($items as $item) {
            $groupCode = $this->resolveSpendingGroupCode($item);
            $mappedItem = $this->mapItemRow($item, $groupCode);

            if (! isset($groupedItems[$groupCode])) {
                $groupedItems[$groupCode] = [
                    'code' => $groupCode,
                    'label' => $mappedItem['KELOMPOK_BELANJA'],
                    'rows' => [],
                    'total_proposed' => 0.0,
                    'total_approved' => 0.0,
                ];
            }

            $groupedItems[$groupCode]['rows'][] = $mappedItem;
            $groupedItems[$groupCode]['total_proposed'] += $mappedItem['NILAI_USULAN'];
            $groupedItems[$groupCode]['total_approved'] += $mappedItem['NILAI_DISETUJUI'];

            $grandProposed += $mappedItem['NILAI_USULAN'];
            $grandApproved += $mappedItem['NILAI_DISETUJUI'];
        }

        $nonEmptyGroups = array_values(array_filter(
            $groupedItems,
            fn (array $group): bool => count($group['rows']) > 0,
        ));

        return [
            'meta' => [
                'header_id' => $header->id,
                'unit_name' => $header->department?->name ?? 'Unit Kerja',
                'fiscal_year' => $header->schedule?->fiscal_year ?? '-',
                'period_range' => $header->schedule?->period_range ?? '-',
                'status' => $header->status,
                'signing_place' => $header->setting?->signing_place ?? 'Bangkinang',
                'unit_head_name' => $header->unitHead?->name ?? '',
                'reviewer_name' => $header->reviewer?->name ?? '',
                'director_name' => $header->setting?->director_name ?? '',
                'director_nrp' => $header->setting?->director_nrp ?? '',
                'approved_at' => $header->approved_at?->format('d/m/Y') ?? '',
            ],
            'daftar_penyesuaian' => [
                'section_a' => $sectionA,
                'section_b' => $sectionB,
            ],
            'belanja' => [
                'groups' => $nonEmptyGroups,
                'grand_total_proposed' => $grandProposed,
                'grand_total_approved' => $grandApproved,
            ],
        ];
    }

    /**
     * Mengekspor dokumen RKA APPROVED ke file Excel (.xlsx) dan mencatat aktivitas export.
     */
    public function export(
        RkaHeader $header,
        User $user,
        ?string $idempotencyKey = null,
    ): BinaryFileResponse {
        $this->validateExportAccess($header, $user);

        $data = $this->prepareWorkbookData($header);
        $filename = $this->buildExportFilename($header);

        $response = Excel::download(new ApprovedRkaTemplateExport($data), $filename);

        $this->activityLogService->log(
            action: RkaLog::ACTION_EXPORT_RKA,
            headerId: $header->id,
            subject: $header,
            description: "Dokumen Excel RKA APPROVED ({$filename}) diekspor.",
            newValues: [
                'filename' => $filename,
                'unit' => $data['meta']['unit_name'],
                'fiscal_year' => $data['meta']['fiscal_year'],
            ],
            userId: $user->id,
            idempotencyKey: $idempotencyKey,
        );

        return $response;
    }

    /**
     * Memetakan satu record RkaAdjustment ke struktur kolom worksheet DAFTAR PENYESUAIAN.
     *
     * @return array<string, mixed>
     */
    private function mapAdjustmentRow(RkaAdjustment $adj, RkaHeader $header): array
    {
        $account = $adj->account ?? $adj->rkaItem?->account;
        $originUnit = $adj->rkaItem?->rkaHeader?->department?->name
            ?? $adj->rkaHeader?->department?->name
            ?? $header->department?->name
            ?? '-';
        $targetUnit = $adj->destinationUnit?->name
            ?? $adj->targetUnit?->name
            ?? '-';

        return [
            'ADJ_REF_ID' => $adj->id,
            'ORIGIN_ITEM_ID' => $adj->rka_item_id,
            'IMPORTED_ITEM_ID' => $adj->imported_rka_item_id,
            'BAGIAN' => $adj->section_type,
            'STATUS_PENYESUAIAN' => $adj->action_type,
            'KODE_REKENING' => $account?->code ?? '-',
            'NAMA_REKENING' => $account?->name ?? '-',
            'URAIAN' => $adj->description ?? '-',
            'UNIT_ASAL' => $originUnit,
            'UNIT_TUJUAN' => $targetUnit,
            'VOLUME' => (float) ($adj->proposed_volume ?? 0),
            'SATUAN' => $adj->unit_symbol ?? '-',
            'HARGA_SATUAN' => (float) ($adj->unit_price ?? 0),
            'JUMLAH_USULAN' => (float) ($adj->proposed_amount ?? 0),
            'JUMLAH_DISETUJUI' => (float) ($adj->approved_amount ?? 0),
            'KETERANGAN_REVIEWER' => $adj->notes ?? '',
            'REVIEWER' => $adj->adjustedBy?->name ?? $header->reviewer?->name ?? '-',
        ];
    }

    /**
     * Menentukan kode kelompok belanja (5.1..5.5, 2.1) untuk satu item.
     */
    private function resolveSpendingGroupCode(RkaItem $item): string
    {
        $formType = trim((string) $item->form_type);
        if (array_key_exists($formType, self::SPENDING_GROUPS)) {
            return $formType;
        }

        $code = (string) ($item->account?->code ?? '');
        foreach (array_keys(self::SPENDING_GROUPS) as $prefix) {
            if (str_starts_with($code, $prefix)) {
                return $prefix;
            }
        }

        return '5.2';
    }

    /**
     * Memetakan satu record RkaItem ke struktur kolom worksheet BELANJA.
     *
     * @return array<string, mixed>
     */
    private function mapItemRow(RkaItem $item, string $groupCode): array
    {
        $account = $item->account;
        $groupHeader = $this->resolveGroupHeader($account, $groupCode);

        $programKegiatan = $item->indicator
            ? trim($item->indicator->program_name.' - '.$item->indicator->activity_name, ' -')
            : '-';

        $proposedVolume = (float) ($item->proposed_volume ?? 0);
        $unitPrice = (float) ($item->unit_price ?? 0);
        $proposedTotal = $item->proposed_total !== null
            ? (float) $item->proposed_total
            : ($proposedVolume * $unitPrice);

        $approvedVolume = (float) ($item->approved_volume ?? 0);
        $approvedTotal = (float) ($item->approved_total ?? 0);
        $approvedUnitPrice = ($approvedVolume > 0 && $approvedTotal > 0)
            ? round($approvedTotal / $approvedVolume, 2)
            : $unitPrice;

        $asalItem = $item->is_imported ? 'IMPORT_SECTION_B' : 'USULAN_ASLI';

        return [
            'ITEM_REF_ID' => $item->id,
            'KELOMPOK_BELANJA' => self::SPENDING_GROUPS[$groupCode] ?? $groupCode,
            'PROGRAM_KEGIATAN' => $programKegiatan,
            'KODE_REKENING' => $account?->code ?? '-',
            'NAMA_REKENING' => $account?->name ?? '-',
            'GROUP_HEADER' => $groupHeader,
            'URAIAN_ITEM' => $item->description ?? '-',
            'VOL_USULAN' => $proposedVolume,
            'SATUAN' => $item->unit_symbol ?? '-',
            'HARGA_SATUAN_USULAN' => $unitPrice,
            'NILAI_USULAN' => $proposedTotal,
            'VOL_DISETUJUI' => $approvedVolume,
            'HARGA_SATUAN_DISETUJUI' => $approvedUnitPrice,
            'NILAI_DISETUJUI' => $approvedTotal,
            'STATUS_ITEM' => $item->status ?? RkaItem::STATUS_PENDING,
            'ASAL_ITEM' => $asalItem,
            'KETERANGAN_REVIEW' => $item->review_notes ?? '',
        ];
    }

    /**
     * Mengambil nama sub-kelompok rekening Level 2 sebagai GROUP_HEADER.
     */
    private function resolveGroupHeader(?Account $account, string $groupCode): string
    {
        $current = $account;
        while ($current !== null) {
            if ((int) $current->level === 2) {
                return "{$current->code} - {$current->name}";
            }
            $current = $current->parent;
        }

        return self::SPENDING_GROUPS[$groupCode] ?? $groupCode;
    }

    /**
     * Menyiapkan struktur data kosong untuk Template Import Approved RKA.
     * Menggunakan struktur workbook yang sama persis dengan exporter PHASE 2.
     *
     * @return array<string, mixed>
     */
    public function prepareBlankImportTemplateData(): array
    {
        return [
            'meta' => [
                'unit_name' => 'TEMPLATE IMPORT RKA',
                'fiscal_year' => now()->format('Y').'/'.now()->addYear()->format('Y'),
                'period_range' => 'PERIODE RKA',
                'status' => RkaHeader::STATUS_APPROVED,
                'signing_place' => 'Bangkinang',
                'approved_at' => '',
                'unit_head_name' => '',
                'reviewer_name' => '',
                'director_name' => '',
                'director_nrp' => '',
            ],
            'daftar_penyesuaian' => [
                'section_a' => [],
                'section_b' => [],
            ],
            'belanja' => [
                'groups' => [],
                'grand_total_proposed' => 0.0,
                'grand_total_approved' => 0.0,
            ],
        ];
    }

    /**
     * Mengunduh file template Excel kosong resmi untuk Import Approved RKA.
     *
     * @throws AuthorizationException
     */
    public function downloadBlankImportTemplate(User $user): BinaryFileResponse
    {
        if (! $user->can('importApproved', RkaHeader::class)) {
            throw new AuthorizationException('Anda tidak memiliki izin untuk mengunduh template Import Approved RKA.');
        }

        return Excel::download(
            new ApprovedRkaTemplateExport($this->prepareBlankImportTemplateData()),
            'TEMPLATE-IMPORT-APPROVED-RKA.xlsx',
            ExcelFormat::XLSX,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
