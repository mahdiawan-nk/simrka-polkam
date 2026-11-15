<?php

namespace App\Jobs;

use App\Models\BackupRun;
use App\Services\Database\DatabaseBackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RunDatabaseBackup implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Maksimal waktu proses backup.
     */
    public int $timeout = 3600;

    /**
     * Backup tidak otomatis diulang untuk menghindari
     * pembuatan file backup ganda.
     */
    public int $tries = 1;

    public function __construct(
        public int $backupRunId
    ) {
        $this->onQueue('backups');
    }

    public function handle(DatabaseBackupService $service): void
    {
        // Claim pekerjaan hanya jika masih pending.
        // Mencegah Job yang sama diproses dua kali.
        $claimed = BackupRun::query()
            ->whereKey($this->backupRunId)
            ->where('status', 'pending')
            ->update([
                'status' => 'processing',
                'started_at' => now(),
                'error_message' => null,
            ]);

        if ($claimed === 0) {
            return;
        }

        $run = BackupRun::findOrFail($this->backupRunId);

        try {
            // Jalankan proses backup melalui Service.
            $service->execute($run);

            $run->refresh();

            $run->update([
                'status' => 'success',
                'completed_at' => now(),
                'duration_seconds' => now()->diffInSeconds(
                    $run->started_at
                ),
                'error_message' => null,
            ]);
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'completed_at' => now(),
                'duration_seconds' => now()->diffInSeconds(
                    $run->started_at
                ),
                'error_message' => $exception->getMessage(),
            ]);

            report($exception);

            throw $exception;
        }
    }

    /**
     * Dipanggil ketika Job dinyatakan gagal oleh Laravel Queue.
     */
    public function failed(?Throwable $exception): void
    {
        $run = BackupRun::find($this->backupRunId);

        if (! $run || $run->status === 'success') {
            return;
        }

        $run->update([
            'status' => 'failed',
            'completed_at' => now(),
            'duration_seconds' => $run->started_at
                ? now()->diffInSeconds($run->started_at)
                : null,
            'error_message' => $exception?->getMessage()
                ?? 'Backup job failed.',
        ]);
    }
}
