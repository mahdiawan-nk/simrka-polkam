<?php

namespace App\Filament\Resources\FundRequests\Pages;

use App\Filament\Resources\FundRequests\Concerns\InteractsWithApprovedRkaFundRequest;
use App\Filament\Resources\FundRequests\FundRequestResource;
use App\Services\Context\ContextManager;
use App\Services\FundRequest\FundRequestBudgetService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class EditFundRequestCustom extends Page
{
    use InteractsWithApprovedRkaFundRequest;
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
        $this->record = $this->resolveRecord($record)->load(['approvals.user', 'items.account', 'items.rkaItem']);

        $user = auth()->user();
        abort_unless($user && $user->can('update', $this->record), 403);

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

        $this->setDepartmentsActive();
        $this->resolveRkaSourceContext();

        $budgetService = app(FundRequestBudgetService::class);
        $rkaItemIds = $this->record->items->pluck('rka_item_id')->filter()->map(fn ($id) => (int) $id)->all();
        $usedByItem = $budgetService->getUsedAmountByRkaItemIds($rkaItemIds, $this->record->id);

        $this->items = [];
        foreach ($this->record->items as $item) {
            $rkaItem = $item->rkaItem;
            $approvedTotal = (float) ($rkaItem?->approved_total ?? $item->rka_approved_total_snapshot ?? 0);
            $approvedVol = (float) ($rkaItem?->approved_volume ?? $item->rka_approved_volume_snapshot ?? 0);
            $remainingAmount = $rkaItem
                ? max(0.0, round($approvedTotal - ($usedByItem[$rkaItem->id] ?? 0.0), 2))
                : 0.0;

            $this->items[] = [
                'id' => $item->id,
                'rka_item_id' => $item->rka_item_id,
                'account_id' => $item->account_id,
                'account_code' => $item->account?->code ?? $item->rka_account_code_snapshot ?? '-',
                'account_name' => $item->account?->name ?? $item->rka_account_name_snapshot ?? '-',
                'description' => $item->description,
                'approved_volume' => $approvedVol,
                'approved_total' => $approvedTotal,
                'remaining_amount' => $remainingAmount,
                'volume' => (float) $item->volume,
                'unit' => $item->unit,
                'unit_price' => (float) $item->unit_price,
                'total_price' => (float) $item->total_price,
                'approved_volume_verified' => $item->approved_volume,
                'approved_price' => $item->approved_price,
                'verification_status' => $item->verification_status,
                'finance_note' => $item->finance_note,
            ];
        }
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

    public function save(): void
    {
        if ($this->rkaSourceError !== null || $this->rkaHeaderId === null) {
            Notification::make()
                ->title('Sumber RKA Approved Tidak Tersedia')
                ->body($this->rkaSourceError ?? 'Belum tersedia RKA yang telah disahkan untuk unit Anda pada periode anggaran aktif.')
                ->danger()
                ->send();

            return;
        }

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
            'items.*.rka_item_id' => 'required|integer|exists:rka_items,id',
            'items.*.volume' => 'required|numeric|min:0.01',
        ]);

        try {
            app(FundRequestBudgetService::class)->updateFundRequest(
                user: auth()->user(),
                fundRequest: $this->record,
                headerData: [
                    'document_number' => $this->document_number,
                    'request_date' => $this->request_date,
                    'activity_name' => $this->activity_name,
                    'execution_date' => $this->execution_date,
                    'responsible_person' => $this->responsible_person,
                    'disbursement_type' => $this->disbursement_type,
                    'recipient_name' => $this->recipient_name,
                    'bank_account_info' => $this->bank_account_info,
                    'attachments' => $this->attachments,
                ],
                rawItems: $this->items,
            );
        } catch (InvalidArgumentException $e) {
            Notification::make()
                ->title('Validasi Anggaran Gagal')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Permohonan Pencairan Dana Berhasil Diperbarui')
            ->success()
            ->send();

        $this->redirect(FundRequestResource::getUrl('index'));
    }
}
