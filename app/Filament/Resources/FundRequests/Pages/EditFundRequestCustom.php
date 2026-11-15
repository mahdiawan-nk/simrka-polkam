<?php

namespace App\Filament\Resources\FundRequests\Pages;

use App\Filament\Resources\FundRequests\FundRequestResource;
use App\Models\Account;
use App\Models\DepartmentBudget;
use App\Services\Context\ContextManager;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Validation\Rule;

class EditFundRequestCustom extends Page
{
    use InteractsWithRecord;

    protected static string $resource = FundRequestResource::class;

    protected string $view = 'filament.resources.fund-requests.pages.edit-fund-request-custom';

    protected static ?string $title = 'Ubah Permohonan Pencairan Dana';

    // Header Form
    public ?string $document_number = '';

    public ?string $request_date = '';

    public ?string $fiscal_year = '';

    // Section A. Kegiatan
    public ?int $department_id = null;

    public ?string $activity_name = '';

    public ?string $execution_date = '';

    public ?string $responsible_person = '';

    public ?string $disbursement_type = '';

    // Section B. Rincian Kebutuhan
    public array $items = [];

    // Section D. Pembayaran Kepada
    public ?string $recipient_name = '';

    public ?string $bank_account_info = '';

    public bool $disableSelectDepartment = false;

    // Section E. Lampiran
    public array $attachments = [];

    public function mount(int|string $record): void
    {
        // 1. Resolve Record dari Route parameter
        $this->record = $this->resolveRecord($record)->load(['approvals.user', 'items']);

        $user = auth()->user();
        abort_unless($user && $user->can('update', $this->record), 403);
        // $this->record = $this->resolveRecord($record);

        // 2. Populate Header & General Info
        $this->document_number = $this->record->document_number;
        $this->request_date = $this->record->request_date ? date('Y-m-d', strtotime($this->record->request_date)) : '';
        $this->fiscal_year = $this->record->fiscal_year;
        $this->department_id = $this->record->department_id;
        $this->activity_name = $this->record->activity_name;
        $this->execution_date = $this->record->execution_date ? date('Y-m-d', strtotime($this->record->execution_date)) : '';
        $this->responsible_person = $this->record->responsible_person;
        $this->disbursement_type = $this->record->disbursement_type;
        $this->recipient_name = $this->record->recipient_name;
        $this->bank_account_info = $this->record->bank_account_info;
        $this->attachments = $this->record->attachments ?? [];

        // 3. Populate Items dari Relasi Database
        $this->items = [];
        foreach ($this->record->items as $item) {
            $this->items[] = [
                'id' => $item->id, // Simpan ID untuk membedakan update/create
                'account_id' => $item->account_id,
                'description' => $item->description,
                'volume' => (float) $item->volume,
                'unit' => $item->unit,
                'unit_price' => (float) $item->unit_price,
                'total_price' => (float) $item->total_price,
                'approved_volume' => $item->approved_volume,
                'approved_price' => $item->approved_price,
                'verification_status' => $item->verification_status,
                'finance_note' => $item->finance_note,
            ];
        }

        // Kunci department_id jika context aktif menentukan department khusus
        $this->setDepartmentsActive();
    }

    public function setDepartmentsActive(): void
    {
        /** @var ContextManager $context */
        $context = app(ContextManager::class);
        $activeDept = $context->getActiveDepartment();

        if ($activeDept) {
            $this->department_id ??= $activeDept->id;
            $this->disableSelectDepartment = true;
        }
    }

    public function addItemRow(): void
    {
        $this->items[] = [
            'id' => null, // Item baru belum memiliki ID
            'account_id' => null,
            'description' => '',
            'volume' => 1,
            'unit' => 'Unit',
            'unit_price' => 0,
            'total_price' => 0,
        ];
    }

    public function removeItemRow(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function updatedItems(): void
    {
        foreach ($this->items as $index => $item) {
            $vol = (float) ($item['volume'] ?? 0);
            $price = (float) ($item['unit_price'] ?? 0);
            $this->items[$index]['total_price'] = $vol * $price;
        }
    }

    public function getTotalRequestedProperty(): float
    {
        return array_sum(array_column($this->items, 'total_price'));
    }

    /**
     * Hitung Rekapitulasi per Kode Akun secara Realtime (Bagian C)
     */
    public function getAccountSummariesProperty(): array
    {
        $summaries = [];

        foreach ($this->items as $item) {
            if (empty($item['account_id'])) {
                continue;
            }

            $accountId = $item['account_id'];
            $totalPrice = (float) ($item['total_price'] ?? 0);
            $status = $item['verification_status'] ?? null;

            // Ambil nominal approved jika ada
            $approvedPrice = (float) ($item['approved_price'] ?? 0);

            if (! isset($summaries[$accountId])) {
                $account = Account::find($accountId);

                $paguAmount = 0;
                if ($this->department_id) {
                    $pagu = DepartmentBudget::where('department_id', $this->department_id)
                        ->where('account_id', $accountId)
                        ->where('fiscal_year', $this->fiscal_year)
                        ->first();
                    $paguAmount = $pagu?->allocated_amount ?? 0;
                }

                $summaries[$accountId] = [
                    'account_code' => $account?->code ?? '-',
                    'account_name' => $account?->name ?? '-',
                    'note' => $account?->managed_by_note ?? '',
                    'total_requested' => 0,
                    'total_approved' => 0,
                    'total_rejected' => 0,
                    'pagu_unit' => $paguAmount,
                ];
            }

            // Akumulasi total dimohonkan
            $summaries[$accountId]['total_requested'] += $totalPrice;

            // Akumulasi berdasarkan status verifikasi
            if (in_array($status, ['approved', 'partial'], true)) {
                $summaries[$accountId]['total_approved'] += $approvedPrice;
            } elseif ($status === 'rejected') {
                $summaries[$accountId]['total_rejected'] += $totalPrice;
            }
        }

        return array_values($summaries);
    }

    public function save(): void
    {
        // Validasi data (document_number di-ignore untuk record saat ini)
        $this->validate([
            'document_number' => [
                'required',
                'string',
                Rule::unique('fund_requests', 'document_number')->ignore($this->record->id),
            ],
            'request_date' => 'required|date',
            'fiscal_year' => 'required|string',
            'department_id' => 'required|exists:departments,id',
            'activity_name' => 'required|string',
            'responsible_person' => 'required|string',
            'recipient_name' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.account_id' => 'required|exists:accounts,id',
            'items.*.description' => 'required|string',
            'items.*.volume' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        // 1. Update Header Permohonan
        $this->record->update([
            'document_number' => $this->document_number,
            'request_date' => $this->request_date,
            'fiscal_year' => $this->fiscal_year,
            'department_id' => $this->department_id,
            'activity_name' => $this->activity_name,
            'execution_date' => $this->execution_date,
            'responsible_person' => $this->responsible_person,
            'disbursement_type' => $this->disbursement_type,
            'recipient_name' => $this->recipient_name,
            'bank_account_info' => $this->bank_account_info,
            'attachments' => $this->attachments,
            'total_requested' => $this->totalRequested,
            'status' => $this->record->status, // Tetap pertahankan status saat ini
        ]);

        // 2. Sinkronisasi Items (Update, Create, Delete)
        $existingItemIds = collect($this->items)->pluck('id')->filter()->toArray();

        // Hapus item dari database yang telah di-remove dari UI oleh user
        $this->record->items()->whereNotIn('id', $existingItemIds)->delete();

        // Update atau Tambah Item Baru
        foreach ($this->items as $item) {
            if (! empty($item['account_id']) && ! empty($item['description'])) {
                $totalPrice = $item['volume'] * $item['unit_price'];

                $this->record->items()->updateOrCreate(
                    ['id' => $item['id'] ?? null],
                    [
                        'account_id' => $item['account_id'],
                        'description' => $item['description'],
                        'volume' => $item['volume'],
                        'unit' => $item['unit'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $totalPrice,
                    ]
                );
            }
        }

        Notification::make()
            ->title('Permohonan Pencairan Dana Berhasil Diperbarui')
            ->success()
            ->send();

        $this->redirect(FundRequestResource::getUrl('index'));
    }
}
