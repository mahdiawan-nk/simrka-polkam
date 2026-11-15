<?php

use App\Jobs\RunDatabaseBackup;
use App\Models\BackupRun;
use App\Models\User;
use App\Services\Database\DatabaseBackupService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

it('executes RunDatabaseBackup job for SQL and CSV formats using DatabaseBackupService (BUG-005)', function () {
    Storage::fake('local');

    $user = User::factory()->create();

    $sqlRun = BackupRun::create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Test SQL Backup',
        'type' => 'table',
        'format' => 'sql',
        'table_names' => ['departments'],
        'status' => 'pending',
        'created_by' => $user->id,
    ]);

    (new RunDatabaseBackup($sqlRun->id))->handle(app(DatabaseBackupService::class));

    $sqlRun->refresh();
    expect($sqlRun->status)->toBe('success')
        ->and($sqlRun->file_count)->toBe(1)
        ->and($sqlRun->total_size)->toBeGreaterThan(0);

    $sqlFile = $sqlRun->files()->first();
    expect($sqlFile)->not->toBeNull()
        ->and($sqlFile->format)->toBe('sql')
        ->and($sqlFile->checksum)->not->toBeNull();
    Storage::disk('local')->assertExists($sqlFile->file_path);

    $csvRun = BackupRun::create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Test CSV Backup',
        'type' => 'table',
        'format' => 'csv',
        'table_names' => ['departments'],
        'status' => 'pending',
        'created_by' => $user->id,
    ]);

    (new RunDatabaseBackup($csvRun->id))->handle(app(DatabaseBackupService::class));

    $csvRun->refresh();
    expect($csvRun->status)->toBe('success')
        ->and($csvRun->file_count)->toBe(1)
        ->and($csvRun->total_size)->toBeGreaterThan(0);

    $csvFile = $csvRun->files()->first();
    expect($csvFile)->not->toBeNull()
        ->and($csvFile->format)->toBe('csv');
    Storage::disk('local')->assertExists($csvFile->file_path);
});
