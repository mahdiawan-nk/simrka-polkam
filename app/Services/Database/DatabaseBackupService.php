<?php

namespace App\Services\Database;

use App\Models\BackupFile;
use App\Models\BackupRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DatabaseBackupService
{
    public function execute(BackupRun $run): void
    {
        $tables = $this->resolveTables($run);

        if ($tables === []) {
            throw new InvalidArgumentException('Tidak ada tabel yang valid untuk dibackup.');
        }

        $disk = 'local';
        $baseDir = 'backups/'.$run->uuid;
        Storage::disk($disk)->makeDirectory($baseDir);

        $fileCount = 0;
        $totalSize = 0;

        if ($run->format === 'csv') {
            foreach ($tables as $table) {
                $file = $this->exportTableToCsv($run, $table, $disk, $baseDir);
                $fileCount++;
                $totalSize += $file->file_size;
            }
        } else {
            if ($run->type === 'table') {
                foreach ($tables as $table) {
                    $file = $this->exportTablesToSql($run, [$table], $table, $disk, $baseDir, $table);
                    $fileCount++;
                    $totalSize += $file->file_size;
                }
            } else {
                $file = $this->exportTablesToSql($run, $tables, 'full_database', $disk, $baseDir, null);
                $fileCount++;
                $totalSize += $file->file_size;
            }
        }

        $run->update([
            'file_count' => $fileCount,
            'total_size' => $totalSize,
        ]);
    }

    /**
     * @return list<string>
     */
    protected function resolveTables(BackupRun $run): array
    {
        $availableTables = collect(DB::connection()->getSchemaBuilder()->getTableListing())
            ->map(fn (string $table): string => str_contains($table, '.') ? Str::afterLast($table, '.') : $table)
            ->values()
            ->all();

        if ($run->type === 'table') {
            $requested = array_values(array_filter((array) $run->table_names));

            return array_values(array_intersect($availableTables, $requested));
        }

        return $availableTables;
    }

    protected function exportTableToCsv(BackupRun $run, string $table, string $disk, string $baseDir): BackupFile
    {
        $fileName = $table.'_'.now()->format('Ymd_His').'.csv';
        $filePath = $baseDir.'/'.$fileName;
        $absolutePath = Storage::disk($disk)->path($filePath);

        $handle = fopen($absolutePath, 'w');
        if ($handle === false) {
            throw new InvalidArgumentException("Gagal membuat file backup CSV untuk tabel {$table}.");
        }

        $rowCount = 0;
        $wroteHeader = false;

        try {
            foreach (DB::table($table)->cursor() as $row) {
                $rowArray = (array) $row;

                if (! $wroteHeader) {
                    fputcsv($handle, array_keys($rowArray));
                    $wroteHeader = true;
                }

                fputcsv($handle, array_map(fn (mixed $value): mixed => is_bool($value) ? (int) $value : $value, $rowArray));
                $rowCount++;
            }

            if (! $wroteHeader) {
                $columns = DB::connection()->getSchemaBuilder()->getColumnListing($table);
                fputcsv($handle, $columns);
            }
        } finally {
            fclose($handle);
        }

        return $this->recordBackupFile($run, $disk, $filePath, $fileName, 'csv', $table, $rowCount);
    }

    /**
     * @param  list<string>  $tables
     */
    protected function exportTablesToSql(
        BackupRun $run,
        array $tables,
        string $namePrefix,
        string $disk,
        string $baseDir,
        ?string $singleTableName
    ): BackupFile {
        $fileName = $namePrefix.'_'.now()->format('Ymd_His').'.sql';
        $filePath = $baseDir.'/'.$fileName;
        $absolutePath = Storage::disk($disk)->path($filePath);

        $handle = fopen($absolutePath, 'w');
        if ($handle === false) {
            throw new InvalidArgumentException("Gagal membuat file backup SQL {$fileName}.");
        }

        $totalRows = 0;
        $driver = DB::connection()->getDriverName();
        $pdo = DB::connection()->getPdo();

        try {
            fwrite($handle, "-- Database Backup: {$run->name}\n");
            fwrite($handle, '-- Generated at: '.now()->toIso8601String()."\n\n");

            if ($driver === 'mysql') {
                fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");
            }

            foreach ($tables as $table) {
                if ($driver === 'mysql') {
                    $createRow = DB::selectOne("SHOW CREATE TABLE `{$table}`");
                    $createSql = $createRow->{'Create Table'} ?? null;
                    if ($createSql) {
                        fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n{$createSql};\n\n");
                    }
                }

                foreach (DB::table($table)->cursor() as $row) {
                    $rowArray = (array) $row;
                    $columns = implode('`, `', array_keys($rowArray));
                    $values = implode(', ', array_map(function (mixed $value) use ($pdo): string {
                        if ($value === null) {
                            return 'NULL';
                        }
                        if (is_bool($value)) {
                            return $value ? '1' : '0';
                        }
                        if (is_int($value) || is_float($value)) {
                            return (string) $value;
                        }

                        return $pdo->quote((string) $value);
                    }, array_values($rowArray)));

                    fwrite($handle, "INSERT INTO `{$table}` (`{$columns}`) VALUES ({$values});\n");
                    $totalRows++;
                }

                fwrite($handle, "\n");
            }

            if ($driver === 'mysql') {
                fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
            }
        } finally {
            fclose($handle);
        }

        return $this->recordBackupFile($run, $disk, $filePath, $fileName, 'sql', $singleTableName, $totalRows);
    }

    protected function recordBackupFile(
        BackupRun $run,
        string $disk,
        string $filePath,
        string $fileName,
        string $format,
        ?string $tableName,
        int $rowCount
    ): BackupFile {
        $absolutePath = Storage::disk($disk)->path($filePath);
        $fileSize = (int) filesize($absolutePath);
        $checksum = hash_file($run->checksum_algorithm ?: 'sha256', $absolutePath) ?: null;

        return BackupFile::create([
            'backup_run_id' => $run->id,
            'uuid' => (string) Str::uuid(),
            'file_name' => $fileName,
            'disk' => $disk,
            'file_path' => $filePath,
            'format' => $format,
            'table_name' => $tableName,
            'file_size' => $fileSize,
            'row_count' => $rowCount,
            'checksum' => $checksum,
            'checksum_algorithm' => $run->checksum_algorithm ?: 'sha256',
            'status' => 'success',
        ]);
    }
}
