<?php

namespace App\Filament\Resources\FundRequests\Pages;

use App\Filament\Resources\FundRequests\Concerns\InteractsWithApprovedRkaFundRequest;
use App\Filament\Resources\FundRequests\FundRequestResource;
use App\Services\Context\ContextManager;
use App\Services\FundRequest\FundRequestBudgetService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use InvalidArgumentException;

class CreateFundRequestCustom extends Page
{
    use InteractsWithApprovedRkaFundRequest;

    protected static string $resource = FundRequestResource::class;

    protected string $view = 'filament.resources.fund-requests.pages.create-fund-request-custom';

    protected static ?string $title = 'Formulir Permohonan Pencairan Dana';

    // Header Form
    public ?string $document_number = '';

    public ?string $request_date = '';

    public ?string $fiscal_year = '';

    // Section A. Kegiatan
    public ?int $department_id = null;

    public ?string $activity_name = '';

    public ?string $execution_date = '';

    public ?string $responsible_person = '';

    public ?string $disbursement_type = 'Uang muka (panjar) – dipertanggungjawabkan kemudian';

    // Section B. Rincian Kebutuhan (berbasis RKA Approved)
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

        $this->setDepartmentsActive();
        $this->resolveRkaSourceContext();
    }

    public function setDepartmentsActive(): void
    {
        /** @var ContextManager $context */
        $context = app(ContextManager::class);
        $activeDept = $context->getActiveDepartment();

        if ($activeDept) {
            $this->department_id = $activeDept->id;
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
            'document_number' => 'required|string|unique:fund_requests,document_number',
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
            app(FundRequestBudgetService::class)->createFundRequest(
                user: auth()->user(),
                headerData: [
                    'document_number' => $this->document_number,
                    'request_date' => $this->request_date,
                    'department_id' => $this->department_id,
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
            ->title('Permohonan Pencairan Dana Berhasil Disimpan')
            ->success()
            ->send();

        $this->redirect(FundRequestResource::getUrl('index'));
    }
}
