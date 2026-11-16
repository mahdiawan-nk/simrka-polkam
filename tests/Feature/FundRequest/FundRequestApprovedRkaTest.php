<?php

use App\Filament\Resources\FundRequests\Pages\CreateFundRequestCustom;
use App\Filament\Resources\FundRequests\Pages\EditFundRequestCustom;
use App\Models\Account;
use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\FundRequest;
use App\Models\RkaHeader;
use App\Models\RkaIndicator;
use App\Models\RkaItem;
use App\Models\RkaSchedule;
use App\Models\RkaSetting;
use App\Models\User;
use App\Services\Context\ContextManager;
use App\Services\FundRequest\FundRequestBudgetService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

function makeFundRequestRkaContext(): array
{
    // Pastikan hanya 1 jadwal aktif selama test berlangsung
    RkaSchedule::query()->where('is_active', true)->update(['is_active' => false]);

    $suffix = strtoupper(substr(uniqid(), -6));

    $pemohonRole = Role::firstOrCreate(['name' => 'pemohon', 'guard_name' => 'web']);
    $keuanganRole = Role::firstOrCreate(['name' => 'keuangan', 'guard_name' => 'web']);
    foreach (['ViewAny:FundRequest', 'View:FundRequest', 'Create:FundRequest', 'Update:FundRequest'] as $permName) {
        $perm = Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
        $pemohonRole->givePermissionTo($perm);
        $keuanganRole->givePermissionTo($perm);
    }

    $ictDept = Department::where('code', 'UPT-ICT')->firstOrFail();
    $p3mDept = Department::where('code', 'P3M')->firstOrFail();

    $ictUser = User::factory()->create(['name' => 'FR Pemohon ICT '.$suffix]);
    $p3mUser = User::factory()->create(['name' => 'FR Pemohon P3M '.$suffix]);
    $keuangan = User::factory()->create(['name' => 'FR Finance '.$suffix]);

    $ictUser->assignRole($pemohonRole);
    $p3mUser->assignRole($pemohonRole);
    $keuangan->assignRole($keuanganRole);

    $ictDept->users()->attach($ictUser->id);
    $p3mDept->users()->attach($p3mUser->id);

    $activeSchedule = RkaSchedule::create([
        'fiscal_year' => '2035/2036',
        'period_range' => 'September 2035 – Agustus 2036',
        'phase_name' => 'FR Active '.$suffix,
        'start_at' => now()->subDays(5),
        'end_at' => now()->addDays(25),
        'is_active' => true,
        'created_by' => $keuangan->id,
    ]);

    $oldSchedule = RkaSchedule::create([
        'fiscal_year' => '2034/2035',
        'period_range' => 'September 2034 – Agustus 2035',
        'phase_name' => 'FR Old '.$suffix,
        'start_at' => now()->subYear(),
        'end_at' => now()->subMonths(2),
        'is_active' => false,
        'created_by' => $keuangan->id,
    ]);

    $setting = RkaSetting::create([
        'setting_name' => 'FR Setting '.$suffix,
        'director_user_id' => $keuangan->id,
        'director_name' => 'Dr. Ir. NINA VERONIKA, ST., MSc',
        'director_nrp' => 'NRP. 110306009',
        'foundation_head_name' => 'EKA SUMAHAMID, ST., MSi',
        'signing_place' => 'Bangkinang',
    ]);

    $account52 = Account::where('code', '5.2')->firstOrFail();
    $detailAccounts = Account::query()->where('code', 'like', '5.2.%')->orderByRaw('LENGTH(code) DESC')->take(2)->get();
    $accA = $detailAccounts[0];
    $accB = $detailAccounts[1] ?? $detailAccounts[0];

    $ictApprovedHeader = RkaHeader::create([
        'schedule_id' => $activeSchedule->id,
        'department_id' => $ictDept->id,
        'setting_id' => $setting->id,
        'unit_head_user_id' => $ictUser->id,
        'status' => RkaHeader::STATUS_APPROVED,
        'is_locked' => true,
        'pagu_budget' => 50000000.0,
    ]);

    $ictIndicator = RkaIndicator::create([
        'rka_header_id' => $ictApprovedHeader->id,
        'account_id' => $account52->id,
        'program_name' => 'Program Layanan ICT',
        'activity_name' => 'Operasional Jaringan',
        'location' => 'Politeknik Kampar',
        'indicator_matrix' => [],
    ]);

    // Item 1: APPROVED (10 x 1.000.000 = 10.000.000)
    $approvedItem = RkaItem::create([
        'rka_header_id' => $ictApprovedHeader->id,
        'rka_indicator_id' => $ictIndicator->id,
        'account_id' => $accA->id,
        'form_type' => '5.2',
        'description' => 'Langganan Cloud Server',
        'proposed_volume' => 10,
        'unit_symbol' => 'Bulan',
        'unit_price' => 1000000,
        'approved_volume' => 10,
        'approved_total' => 10000000,
        'status' => RkaItem::STATUS_APPROVED,
        'is_trimmed' => false,
    ]);

    // Item 1b: APPROVED dengan approved_volume NULL (pola Excel RKA asli yang disetujui penuh sesuai proposed_volume)
    $approvedNullVolItem = RkaItem::create([
        'rka_header_id' => $ictApprovedHeader->id,
        'rka_indicator_id' => $ictIndicator->id,
        'account_id' => $accB->id,
        'form_type' => '5.2',
        'description' => 'Pembayaran DOI Jurnal',
        'proposed_volume' => 10,
        'unit_symbol' => 'Artikel',
        'unit_price' => 200000,
        'approved_volume' => null,
        'approved_total' => 2000000,
        'status' => RkaItem::STATUS_APPROVED,
        'is_trimmed' => false,
    ]);

    // Item 2: TRIMMED pada rekening yang sama dengan pagu rekening dibatasi (5 x 2.000.000 = 10.000.000)
    $trimmedItem = RkaItem::create([
        'rka_header_id' => $ictApprovedHeader->id,
        'rka_indicator_id' => $ictIndicator->id,
        'account_id' => $accA->id,
        'form_type' => '5.2',
        'description' => 'Lisensi Software Keamanan',
        'proposed_volume' => 10,
        'unit_symbol' => 'Lisensi',
        'unit_price' => 2000000,
        'approved_volume' => 5,
        'approved_total' => 10000000,
        'status' => 'TRIMMED',
        'is_trimmed' => true,
    ]);

    // Item 3: REJECTED (approved_total = 0 -> tidak boleh dipilih)
    $rejectedItem = RkaItem::create([
        'rka_header_id' => $ictApprovedHeader->id,
        'rka_indicator_id' => $ictIndicator->id,
        'account_id' => $accB->id,
        'form_type' => '5.2',
        'description' => 'Item Ditolak Reviewer',
        'proposed_volume' => 2,
        'unit_symbol' => 'Unit',
        'unit_price' => 5000000,
        'approved_volume' => 0,
        'approved_total' => 0,
        'status' => RkaItem::STATUS_REJECTED,
        'is_trimmed' => true,
    ]);

    // Item 4: Zero approved_volume -> tidak boleh dipilih
    $zeroVolItem = RkaItem::create([
        'rka_header_id' => $ictApprovedHeader->id,
        'rka_indicator_id' => $ictIndicator->id,
        'account_id' => $accB->id,
        'form_type' => '5.2',
        'description' => 'Item Volume Nol',
        'proposed_volume' => 1,
        'unit_symbol' => 'Unit',
        'unit_price' => 1000000,
        'approved_volume' => 0,
        'approved_total' => 1000000,
        'status' => RkaItem::STATUS_APPROVED,
        'is_trimmed' => false,
    ]);

    // DepartmentBudget untuk accA: 12.000.000 (lebih kecil dari gabungan 2 item = 20jt -> uji kontrol rekening!)
    DepartmentBudget::updateOrCreate(
        [
            'department_id' => $ictDept->id,
            'account_id' => $accA->id,
            'fiscal_year' => $activeSchedule->fiscal_year,
        ],
        [
            'schedule_id' => $activeSchedule->id,
            'allocated_amount' => 12000000.0,
        ]
    );

    // RKA Approved periode lama & RKA Draft/Submitted unit lain (tidak boleh digunakan)
    $oldApprovedHeader = RkaHeader::create([
        'schedule_id' => $oldSchedule->id,
        'department_id' => $ictDept->id,
        'setting_id' => $setting->id,
        'unit_head_user_id' => $ictUser->id,
        'status' => RkaHeader::STATUS_APPROVED,
        'is_locked' => true,
        'pagu_budget' => 10000000.0,
    ]);

    $p3mApprovedHeader = RkaHeader::create([
        'schedule_id' => $activeSchedule->id,
        'department_id' => $p3mDept->id,
        'setting_id' => $setting->id,
        'unit_head_user_id' => $p3mUser->id,
        'status' => RkaHeader::STATUS_APPROVED,
        'is_locked' => true,
        'pagu_budget' => 20000000.0,
    ]);
    $p3mIndicator = RkaIndicator::create([
        'rka_header_id' => $p3mApprovedHeader->id,
        'account_id' => $account52->id,
        'program_name' => 'Program P3M',
        'activity_name' => 'Penelitian',
        'location' => 'Politeknik Kampar',
        'indicator_matrix' => [],
    ]);
    $p3mItem = RkaItem::create([
        'rka_header_id' => $p3mApprovedHeader->id,
        'rka_indicator_id' => $p3mIndicator->id,
        'account_id' => $accA->id,
        'form_type' => '5.2',
        'description' => 'Item Milik P3M',
        'proposed_volume' => 1,
        'unit_symbol' => 'Paket',
        'unit_price' => 5000000,
        'approved_volume' => 1,
        'approved_total' => 5000000,
        'status' => RkaItem::STATUS_APPROVED,
    ]);

    return compact(
        'ictDept',
        'p3mDept',
        'ictUser',
        'p3mUser',
        'keuangan',
        'activeSchedule',
        'oldSchedule',
        'setting',
        'accA',
        'accB',
        'ictApprovedHeader',
        'approvedItem',
        'approvedNullVolItem',
        'trimmedItem',
        'rejectedItem',
        'zeroVolItem',
        'oldApprovedHeader',
        'p3mApprovedHeader',
        'p3mItem',
    );
}

it('resolves only APPROVED RKA from active schedule and blocks missing, draft, submitted, old, or ambiguous schedules', function () {
    $ctx = makeFundRequestRkaContext();
    $service = app(FundRequestBudgetService::class);
    app(ContextManager::class)->setActiveDepartment($ctx['ictDept']->id);

    // 1. RKA Approved periode aktif berhasil di-resolve
    $resolved = $service->resolveApprovedRkaContext($ctx['ictUser']);
    expect($resolved['rka_header']->id)->toBe($ctx['ictApprovedHeader']->id);

    // 2 & 3. RKA DRAFT dan SUBMITTED tidak dapat digunakan
    $ctx['ictApprovedHeader']->update(['status' => RkaHeader::STATUS_DRAFT]);
    expect(fn () => $service->resolveApprovedRkaContext($ctx['ictUser']))
        ->toThrow(InvalidArgumentException::class, 'Belum tersedia RKA yang telah disahkan');

    $ctx['ictApprovedHeader']->update(['status' => RkaHeader::STATUS_SUBMITTED]);
    expect(fn () => $service->resolveApprovedRkaContext($ctx['ictUser']))
        ->toThrow(InvalidArgumentException::class, 'Belum tersedia RKA yang telah disahkan');

    $ctx['ictApprovedHeader']->update(['status' => RkaHeader::STATUS_APPROVED]);

    // 7. Tidak ada jadwal aktif -> ditolak (tidak fallback ke oldSchedule)
    $ctx['activeSchedule']->update(['is_active' => false]);
    expect(fn () => $service->resolveApprovedRkaContext($ctx['ictUser']))
        ->toThrow(InvalidArgumentException::class, 'Tidak ada periode anggaran RKA yang sedang aktif');

    // 8. Lebih dari satu jadwal aktif -> ambigu, ditolak
    $ctx['activeSchedule']->update(['is_active' => true]);
    $ctx['oldSchedule']->update(['is_active' => true]);
    expect(fn () => $service->resolveApprovedRkaContext($ctx['ictUser']))
        ->toThrow(InvalidArgumentException::class, 'lebih dari satu periode anggaran RKA yang aktif');
});

it('filters selectable RKA items to APPROVED/TRIMMED with positive volume/total and blocks rejected, zero, or cross-unit items', function () {
    $ctx = makeFundRequestRkaContext();
    $service = app(FundRequestBudgetService::class);

    $selectable = $service->getSelectableRkaItems($ctx['ictApprovedHeader']);
    $ids = $selectable->pluck('rka_item_id')->all();

    expect($ids)->toContain($ctx['approvedItem']->id)
        ->toContain($ctx['approvedNullVolItem']->id)
        ->toContain($ctx['trimmedItem']->id)
        ->not->toContain($ctx['rejectedItem']->id)
        ->not->toContain($ctx['zeroVolItem']->id)
        ->not->toContain($ctx['p3mItem']->id);

    // Pastikan item APPROVED dengan approved_volume NULL tetap menghitung approved_unit_price dari proposed_volume
    $nullVolRow = $selectable->firstWhere('rka_item_id', $ctx['approvedNullVolItem']->id);
    expect((float) $nullVolRow['approved_volume'])->toBe(10.0)
        ->and((float) $nullVolRow['approved_unit_price'])->toBe(200000.0);

    // Manipulasi request memaksa item milik unit lain (P3M) atau unit_price palsu ditolak
    expect(fn () => $service->createFundRequest(
        $ctx['ictUser'],
        [
            'document_number' => 'FR-CROSS-'.uniqid(),
            'request_date' => now()->format('Y-m-d'),
            'department_id' => $ctx['ictDept']->id,
            'activity_name' => 'Uji Lintas Unit',
            'responsible_person' => 'Kepala ICT',
            'disbursement_type' => 'Uang muka',
            'recipient_name' => 'Bendahara',
        ],
        [
            ['rka_item_id' => $ctx['p3mItem']->id, 'volume' => 1, 'unit_price' => 100],
        ]
    ))->toThrow(InvalidArgumentException::class, 'bukan milik RKA Approved unit Anda');
});

it('enforces two-level budget control (per item and per DepartmentBudget account), ignores browser price manipulation, and releases balance on rejection', function () {
    $ctx = makeFundRequestRkaContext();
    $service = app(FundRequestBudgetService::class);

    // 1. Pengajuan valid pada approvedItem: 3 bulan x 1.000.000 = 3.000.000 (browser coba kirim unit_price = 100 -> diabaikan, pakai 1.000.000!)
    $fr1 = $service->createFundRequest(
        $ctx['ictUser'],
        [
            'document_number' => 'FR-001-'.uniqid(),
            'request_date' => now()->format('Y-m-d'),
            'department_id' => $ctx['ictDept']->id,
            'activity_name' => 'Bayar Cloud',
            'responsible_person' => 'Kepala ICT',
            'disbursement_type' => 'Uang muka',
            'recipient_name' => 'Vendor',
        ],
        [
            ['rka_item_id' => $ctx['approvedItem']->id, 'volume' => 3, 'unit_price' => 100],
        ]
    );

    expect($fr1->rka_header_id)->toBe($ctx['ictApprovedHeader']->id)
        ->and((float) $fr1->total_requested)->toBe(3000000.0)
        ->and($fr1->items->first()->rka_item_id)->toBe($ctx['approvedItem']->id)
        ->and((float) $fr1->items->first()->unit_price)->toBe(1000000.0);

    // 2. Kontrol Per Item: sisa approvedItem = 7.000.000. Ajukan 8.000.000 (8 bulan) -> DITOLAK
    expect(fn () => $service->createFundRequest(
        $ctx['ictUser'],
        [
            'document_number' => 'FR-OVER-ITEM-'.uniqid(),
            'request_date' => now()->format('Y-m-d'),
            'department_id' => $ctx['ictDept']->id,
            'activity_name' => 'Over Item',
            'responsible_person' => 'Kepala ICT',
            'disbursement_type' => 'Uang muka',
            'recipient_name' => 'Vendor',
        ],
        [
            ['rka_item_id' => $ctx['approvedItem']->id, 'volume' => 8],
        ]
    ))->toThrow(InvalidArgumentException::class, 'melebihi sisa anggaran item');

    // 3. Kontrol Per Rekening: pagu rekening accA = 12.000.000, terpakai 3.000.000, sisa = 9.000.000.
    // trimmedItem punya sisa item 10.000.000 (5 x 2jt), ajukan 5 x 2jt = 10.000.000 -> lolos item, DITOLAK rekening!
    expect(fn () => $service->createFundRequest(
        $ctx['ictUser'],
        [
            'document_number' => 'FR-OVER-ACC-'.uniqid(),
            'request_date' => now()->format('Y-m-d'),
            'department_id' => $ctx['ictDept']->id,
            'activity_name' => 'Over Rekening',
            'responsible_person' => 'Kepala ICT',
            'disbursement_type' => 'Uang muka',
            'recipient_name' => 'Vendor',
        ],
        [
            ['rka_item_id' => $ctx['trimmedItem']->id, 'volume' => 5],
        ]
    ))->toThrow(InvalidArgumentException::class, 'melebihi sisa pagu rekening');

    // 4. Penolakan (status = rejected) melepaskan saldo kembali
    $fr1->update(['status' => 'rejected']);
    $fr2 = $service->createFundRequest(
        $ctx['ictUser'],
        [
            'document_number' => 'FR-AFTER-REJECT-'.uniqid(),
            'request_date' => now()->format('Y-m-d'),
            'department_id' => $ctx['ictDept']->id,
            'activity_name' => 'Setelah Reject',
            'responsible_person' => 'Kepala ICT',
            'disbursement_type' => 'Uang muka',
            'recipient_name' => 'Vendor',
        ],
        [
            ['rka_item_id' => $ctx['trimmedItem']->id, 'volume' => 5],
        ]
    );
    expect((float) $fr2->total_requested)->toBe(10000000.0);

    // 5. Edit Fund Request mengecualikan transaksi yang sedang diedit (5 -> 4 unit = 8.000.000 berhasil)
    $updated = $service->updateFundRequest(
        $ctx['ictUser'],
        $fr2,
        [
            'document_number' => $fr2->document_number,
            'request_date' => now()->format('Y-m-d'),
            'activity_name' => 'Setelah Edit',
            'execution_date' => now()->addDays(7)->format('Y-m-d'),
            'responsible_person' => 'Kepala ICT',
            'disbursement_type' => 'Uang muka',
            'recipient_name' => 'Vendor',
        ],
        [
            ['id' => $fr2->items->first()->id, 'rka_item_id' => $ctx['trimmedItem']->id, 'volume' => 4],
        ]
    );
    expect((float) $updated->total_requested)->toBe(8000000.0);
});

it('supports CreateFundRequestCustom and EditFundRequestCustom Livewire pages with RKA item picker and automatic account summary', function () {
    $ctx = makeFundRequestRkaContext();
    $this->actingAs($ctx['ictUser']);
    app(ContextManager::class)->setActiveDepartment($ctx['ictDept']->id);

    $docNo = 'FR-LW-'.uniqid();

    Livewire::test(CreateFundRequestCustom::class)
        ->assertOk()
        ->assertSet('rkaHeaderId', $ctx['ictApprovedHeader']->id)
        ->assertSee('+ Pilih Item RKA')
        ->assertSee('fi-modal-slide-over', false)
        ->assertSee('Pilih Item dari RKA Approved')
        ->assertSee('2.1 — Pembayaran Kewajiban Jangka Pendek')
        ->call('selectRkaItem', $ctx['approvedItem']->id)
        ->set('itemSearch', 'Tidak Ada Item Ini')
        ->assertSee('Item tidak ditemukan')
        ->call('resetItemFilters')
        ->assertSet('itemSearch', '')
        ->assertCount('items', 1)
        ->set('items.0.volume', 2)
        ->set('document_number', $docNo)
        ->set('activity_name', 'Operasional Cloud')
        ->set('responsible_person', 'Kepala ICT')
        ->set('recipient_name', 'PT Cloud')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $created = FundRequest::where('document_number', $docNo)->firstOrFail();
    expect($created->rka_header_id)->toBe($ctx['ictApprovedHeader']->id)
        ->and((float) $created->total_requested)->toBe(2000000.0)
        ->and($created->items->first()->rka_item_id)->toBe($ctx['approvedItem']->id);

    Livewire::test(EditFundRequestCustom::class, ['record' => $created->id])
        ->assertOk()
        ->assertSet('rkaHeaderId', $ctx['ictApprovedHeader']->id)
        ->set('items.0.volume', 4)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect((float) $created->fresh()->total_requested)->toBe(4000000.0);
});
