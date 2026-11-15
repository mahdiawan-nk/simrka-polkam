<?php

namespace App\Http\Controllers;

use App\Filament\Pages\Database\BackupManagement;
use App\Models\BackupFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatabaseBackupDownloadController extends Controller
{
    public function __invoke(BackupFile $backupFile): StreamedResponse
    {
        abort_unless(BackupManagement::canAccess(), 403, 'Anda tidak memiliki izin untuk mengunduh file backup.');

        $backupRun = $backupFile->backupRun;
        abort_if(
            ! $backupRun || $backupRun->status !== 'success' || $backupFile->status !== 'success',
            404,
            'File backup tidak tersedia untuk diunduh.'
        );

        $disk = $backupFile->disk ?: 'local';
        $path = (string) $backupFile->file_path;

        abort_if(
            $path === '' || str_contains($path, '..') || str_starts_with($path, '/'),
            404,
            'File backup tidak valid.'
        );

        if (! Storage::disk($disk)->exists($path) || Storage::disk($disk)->size($path) <= 0) {
            abort(404, 'File backup tidak ditemukan atau kosong.');
        }

        $safeFileName = basename((string) $backupFile->file_name);
        $extension = strtolower(pathinfo($safeFileName, PATHINFO_EXTENSION));

        $contentType = match ($extension) {
            'sql' => 'application/sql',
            'csv' => 'text/csv; charset=UTF-8',
            default => 'application/octet-stream',
        };

        return Storage::disk($disk)->download(
            $path,
            $safeFileName,
            [
                'Content-Type' => $contentType,
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}
