<?php

namespace App\Filament\Resources\FundRequests\Pages;

use App\Filament\Resources\FundRequests\FundRequestResource;
use App\Models\Account;
use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\FundRequest;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use App\Services\Context\ContextManager;

class CreateFundRequestCustom extends Page
{
    protected static string $resource = FundRequestResource::class;

    protected string $view = 'filament.resources.fund-requests.pages.create-fund-request-custom';

    protected static ?string $title = 'Formulir Permohonan Pencairan Dana';

    // Header Form
    public ?string $document_number = '';
    public ?string $request_date = '';
    public ?string $fiscal_year = '2026/2027';

    // Section A. Kegiatan
    public ?int $department_id = null;
    public ?string $activity_name = '';
    public ?string $execution_date = '';
    public ?string $responsible_person = '';
    public ?string $disbursement_type = 'Uang muka (panjar) – dipertanggungjawabkan kemudian';

    // Section B. Rincian Kebutuhan
    public array $items = [];

    // Section D. Pembayaran Kepada
    public ?string $recipient_name = '';
    public ?string $bank_account_info = '';

    public bool $disableSelectDepartment = false;
    // Section E. Lampiran
    public array $attachments = [];

    public function mount(): void
    {
        $this->request_date = now()->format('Y-m-d');
        $this->execution_date = now()->addDays(14)->format('Y-m-d');
        
        // Default 5 baris kosong rincian kebutuhan
        for ($i = 0; $i < 5; $i++) {
            $this->addItemRow();
        }

        // Set department aktif dari context manager
        $this->setDepartmentsActive();
    }

    public function setDepartmentsActive()
    {
        /** @var ContextManager $context */
        $context = app(ContextManager::class);
        $activeDept = $context->getActiveDepartment();

        if ($activeDept) {
            $this->department_id = $activeDept->id;
            $this->disableSelectDepartment = true;
        }
    }

    public function addItemRow(): void
    {
        $this->items[] = [
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

            if (!isset($summaries[$accountId])) {
                $account = Account::find($accountId);
                
                // Cari pagu department untuk akun ini
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
                    'pagu_unit' => $paguAmount,
                ];
            }

            $summaries[$accountId]['total_requested'] += $totalPrice;
        }

        return array_values($summaries);
    }

    public function save(): void
    {
        $this->validate([
            'document_number' => 'required|string|unique:fund_requests,document_number',
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

        $fundRequest = FundRequest::create([
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
            'status' => 'submitted',
            'created_by' => auth()->id(),
        ]);

        foreach ($this->items as $item) {
            if (!empty($item['account_id']) && !empty($item['description'])) {
                $fundRequest->items()->create([
                    'account_id' => $item['account_id'],
                    'description' => $item['description'],
                    'volume' => $item['volume'],
                    'unit' => $item['unit'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['volume'] * $item['unit_price'],
                ]);
            }
        }

        Notification::make()
            ->title('Permohonan Pencairan Dana Berhasil Disimpan')
            ->success()
            ->send();

        $this->redirect(FundRequestResource::getUrl('index'));
    }
}