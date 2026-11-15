<?php

namespace App\Services;

use App\Models\RkaLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Layanan terpusat untuk mencatat riwayat aktivitas RKA Header dan seluruh entitas turunannya.
 */
class RkaActivityLogService
{
    /**
     * Daftar kunci sensitif yang tidak boleh tersimpan di payload audit log.
     *
     * @var array<int, string>
     */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'remember_token',
        'api_token',
        'secret',
        'token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Mencatat satu entri aktivitas RKA ke tabel `rka_logs`.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(
        string $action,
        ?int $headerId = null,
        ?Model $subject = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null,
        ?string $idempotencyKey = null,
    ): RkaLog {
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $existing = RkaLog::query()
                ->where('rka_header_id', $headerId)
                ->where('action', $action)
                ->where('new_values->idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing;
            }

            $newValues = array_merge($newValues ?? [], ['idempotency_key' => $idempotencyKey]);
        }

        return RkaLog::record(
            action: $action,
            headerId: $headerId,
            oldValues: $this->sanitize($oldValues),
            newValues: $this->sanitize($newValues),
            userId: $userId,
            subject: $subject,
            description: $description,
        );
    }

    /**
     * Menyaring field sensitif dari payload sebelum disimpan ke JSON.
     *
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function sanitize(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $sanitized = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                continue;
            }

            $sanitized[$key] = is_array($value) ? $this->sanitize($value) : $value;
        }

        return $sanitized;
    }
}
