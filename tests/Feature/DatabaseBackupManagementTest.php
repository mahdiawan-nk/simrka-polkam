<?php

use App\Filament\Pages\Database\BackupManagement;
use App\Models\BackupFile;
use App\Models\BackupRun;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

it('denies BackupManagement page for pemohon', function () {
    $pemohonRole = Role::firstOrCreate(['name' => 'pemohon', 'guard_name' => 'web']);
    $pemohon = User::factory()->create();
    $pemohon->assignRole($pemohonRole);

    $this->actingAs($pemohon)
        ->get('/admin/database/backup')
        ->assertForbidden();
});

it('renders BackupManagement page and opens detail modal for super_admin', function () {
    $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole($superAdminRole);

    $run = BackupRun::create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Backup Livewire Test',
        'type' => 'full',
        'format' => 'sql',
        'status' => 'success',
        'file_count' => 0,
        'total_size' => 0,
        'created_by' => $superAdmin->id,
    ]);

    $this->actingAs($superAdmin);

    Livewire::test(BackupManagement::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$run])
        ->callTableAction('detail', $run)
        ->assertSuccessful();
});

it('renders backup-detail view for success, empty-files, and failed backups without exposing internal paths', function () {
    Storage::fake('local');

    $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $superAdmin = User::factory()->create(['name' => 'Admin Penguji']);
    $superAdmin->assignRole($superAdminRole);

    $this->actingAs($superAdmin);

    $secretInternalPath = 'backups/internal-secret-dir/backup_test.sql';
    Storage::disk('local')->put($secretInternalPath, 'SELECT 1;');

    $successRun = BackupRun::create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Backup Sukses Harian',
        'description' => 'Backup rutin',
        'type' => 'full',
        'format' => 'sql',
        'status' => 'success',
        'file_count' => 1,
        'total_size' => 2048,
        'started_at' => now()->subSeconds(10),
        'completed_at' => now(),
        'duration_seconds' => 10,
        'created_by' => $superAdmin->id,
    ]);

    $file = BackupFile::create([
        'backup_run_id' => $successRun->id,
        'uuid' => (string) Str::uuid(),
        'file_name' => 'backup_test.sql',
        'disk' => 'local',
        'file_path' => $secretInternalPath,
        'format' => 'sql',
        'file_size' => 2048,
        'status' => 'success',
    ]);

    $successHtml = view('filament.pages.database.backup-detail', [
        'record' => $successRun->loadMissing(['files', 'creator']),
    ])->render();

    expect($successHtml)
        ->toContain('Backup Sukses Harian')
        ->toContain('SUCCESS')
        ->toContain('Full Database')
        ->toContain('10 detik')
        ->toContain('2 KB')
        ->toContain('backup_test.sql')
        ->toContain('Download')
        ->not->toContain($secretInternalPath);

    $emptyRun = BackupRun::create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Backup Tanpa File',
        'type' => 'table',
        'format' => 'csv',
        'table_names' => ['departments'],
        'status' => 'pending',
        'file_count' => 0,
        'total_size' => 0,
        'created_by' => $superAdmin->id,
    ]);

    $emptyHtml = view('filament.pages.database.backup-detail', [
        'record' => $emptyRun->loadMissing(['files', 'creator']),
    ])->render();

    expect($emptyHtml)
        ->toContain('Backup Tanpa File')
        ->toContain('Belum ada file backup yang tersedia untuk riwayat ini.');

    $failedRun = BackupRun::create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Backup Gagal',
        'type' => 'full',
        'format' => 'sql',
        'status' => 'failed',
        'file_count' => 0,
        'total_size' => 0,
        'error_message' => 'Koneksi database terputus saat proses dump.',
        'created_by' => $superAdmin->id,
    ]);

    $failedHtml = view('filament.pages.database.backup-detail', [
        'record' => $failedRun->loadMissing(['files', 'creator']),
    ])->render();

    expect($failedHtml)
        ->toContain('Backup Gagal')
        ->toContain('FAILED')
        ->toContain('Koneksi database terputus saat proses dump.');
});

it('enforces authorization, headers, content integrity, and validation on backup file download route', function () {
    Storage::fake('local');

    $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $pemohonRole = Role::firstOrCreate(['name' => 'pemohon', 'guard_name' => 'web']);

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole($superAdminRole);

    $pemohon = User::factory()->create();
    $pemohon->assignRole($pemohonRole);

    $run = BackupRun::create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Backup Download Test',
        'type' => 'full',
        'format' => 'sql',
        'status' => 'success',
        'file_count' => 1,
        'total_size' => 128,
        'created_by' => $superAdmin->id,
    ]);

    $sqlContent = "-- Database Backup Test\nSET FOREIGN_KEY_CHECKS=0;\nSELECT 1;\n";
    $path = 'backups/'.$run->uuid.'/full_database_test.sql';
    Storage::disk('local')->put($path, $sqlContent);

    $file = BackupFile::create([
        'backup_run_id' => $run->id,
        'uuid' => (string) Str::uuid(),
        'file_name' => 'full_database_test.sql',
        'disk' => 'local',
        'file_path' => $path,
        'format' => 'sql',
        'file_size' => strlen($sqlContent),
        'status' => 'success',
    ]);

    // Unauthorized user gets 403
    $this->actingAs($pemohon)
        ->get(route('database.backups.files.download', $file))
        ->assertForbidden();

    // Unauthenticated user is blocked (403) without 500 Route [login] error
    auth()->logout();
    $this->get(route('database.backups.files.download', $file))
        ->assertForbidden();

    // Authorized super_admin gets 200 with attachment headers and identical SQL content
    $response = $this->actingAs($superAdmin)
        ->get(route('database.backups.files.download', $file));

    $response->assertSuccessful()
        ->assertHeader('Content-Type', 'application/sql')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertDownload('full_database_test.sql');

    expect($response->headers->get('Content-Disposition'))
        ->toContain('attachment')
        ->toContain('full_database_test.sql')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->streamedContent())->toBe($sqlContent);

    // Empty (0-byte) file is rejected with 404
    Storage::disk('local')->put($path, '');
    $this->actingAs($superAdmin)
        ->get(route('database.backups.files.download', $file))
        ->assertNotFound();

    // Path traversal is rejected with 404
    $traversalFile = BackupFile::create([
        'backup_run_id' => $run->id,
        'uuid' => (string) Str::uuid(),
        'file_name' => 'evil.sql',
        'disk' => 'local',
        'file_path' => '../../.env',
        'format' => 'sql',
        'file_size' => 10,
        'status' => 'success',
    ]);
    $this->actingAs($superAdmin)
        ->get(route('database.backups.files.download', $traversalFile))
        ->assertNotFound();

    // Failed BackupRun is rejected with 404
    Storage::disk('local')->put($path, $sqlContent);
    $run->update(['status' => 'failed']);
    $this->actingAs($superAdmin)
        ->get(route('database.backups.files.download', $file))
        ->assertNotFound();

    // Missing file on disk is rejected with 404
    $run->update(['status' => 'success']);
    Storage::disk('local')->delete($path);
    $this->actingAs($superAdmin)
        ->get(route('database.backups.files.download', $file))
        ->assertNotFound();
});
