<?php

namespace App\Services\Branding;

use App\Models\BrandSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class BrandSettingsService
{
    public const CACHE_KEY = 'brand_settings.active';

    public const STORAGE_DISK = 'public';

    public const STORAGE_DIR = 'branding';

    /**
     * @var list<string>
     */
    public const MEDIA_FIELDS = [
        'logo_path',
        'logo_light_path',
        'logo_dark_path',
        'logo_compact_path',
        'login_logo_path',
        'favicon_path',
        'document_logo_path',
    ];

    /**
     * @return array<string, mixed>
     */
    public function getDefaults(): array
    {
        return [
            'brand_name' => 'e-RKA',
            'app_full_name' => 'Sistem Perencanaan & Anggaran',
            'institution_name' => 'Politeknik Kampar',
            'institution_short_name' => 'SIMRKA',
            'tagline' => 'Sistem Perencanaan dan Anggaran',
            'short_description' => 'Platform terpadu perencanaan RKA, pengendalian pagu anggaran, dan pencairan dana institusi secara akuntabel.',
            'browser_title' => 'SIMRKA / e-RKA — Politeknik Kampar',
            'logo_path' => null,
            'logo_light_path' => null,
            'logo_dark_path' => null,
            'logo_compact_path' => null,
            'login_logo_path' => null,
            'favicon_path' => null,
            'document_institution_name' => 'Politeknik Kampar',
            'document_logo_path' => null,
            'document_tagline' => 'Sistem Perencanaan dan Anggaran',
            'document_footer_text' => 'Dokumen RKA Politeknik Kampar',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        $defaults = $this->getDefaults();

        try {
            return Cache::rememberForever(self::CACHE_KEY, function () use ($defaults): array {
                if (! Schema::hasTable('brand_settings')) {
                    return $defaults;
                }

                $record = BrandSetting::query()->latest('id')->first();

                if (! $record) {
                    return $defaults;
                }

                $merged = $defaults;
                foreach (array_keys($defaults) as $key) {
                    $value = $record->{$key};
                    if ($value !== null && $value !== '') {
                        $merged[$key] = $value;
                    }
                }

                return $merged;
            });
        } catch (Throwable) {
            return $defaults;
        }
    }

    public function getBrandName(): string
    {
        return (string) ($this->getSettings()['brand_name'] ?? 'e-RKA');
    }

    public function getAppFullName(): string
    {
        return (string) ($this->getSettings()['app_full_name'] ?? 'Sistem Perencanaan & Anggaran');
    }

    public function getInstitutionName(): string
    {
        return (string) ($this->getSettings()['institution_name'] ?? 'Politeknik Kampar');
    }

    public function getInstitutionShortName(): string
    {
        return (string) ($this->getSettings()['institution_short_name'] ?? 'SIMRKA');
    }

    public function getTagline(): string
    {
        return (string) ($this->getSettings()['tagline'] ?? 'Sistem Perencanaan dan Anggaran');
    }

    public function getShortDescription(): string
    {
        return (string) ($this->getSettings()['short_description'] ?? $this->getDefaults()['short_description']);
    }

    public function getBrowserTitle(): string
    {
        return (string) ($this->getSettings()['browser_title'] ?? 'SIMRKA / e-RKA — Politeknik Kampar');
    }

    public function getLogoUrl(string $variant = 'primary'): ?string
    {
        $settings = $this->getSettings();

        $path = match ($variant) {
            'light' => $settings['logo_light_path'] ?? $settings['logo_path'] ?? null,
            'dark' => $settings['logo_dark_path'] ?? $settings['logo_path'] ?? null,
            'compact' => $settings['logo_compact_path'] ?? $settings['logo_path'] ?? null,
            'login' => $settings['login_logo_path'] ?? $settings['logo_path'] ?? null,
            'document' => $settings['document_logo_path'] ?? $settings['logo_path'] ?? null,
            default => $settings['logo_path'] ?? null,
        };

        return $this->resolvePublicUrl($path);
    }

    public function getFaviconUrl(): ?string
    {
        $path = $this->getSettings()['favicon_path'] ?? null;

        return $this->resolvePublicUrl($path);
    }

    /**
     * @return array{institution_name: string, logo_url: ?string, tagline: string, footer_text: string}
     */
    public function getDocumentBranding(): array
    {
        $settings = $this->getSettings();

        return [
            'institution_name' => (string) ($settings['document_institution_name'] ?: $settings['institution_name']),
            'logo_url' => $this->getLogoUrl('document'),
            'tagline' => (string) ($settings['document_tagline'] ?: $settings['tagline']),
            'footer_text' => (string) ($settings['document_footer_text'] ?: ('Dokumen '.$this->getInstitutionName())),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateSettings(array $data, ?int $userId = null): BrandSetting
    {
        $this->validateUploadedSvgs($data);

        $existing = BrandSetting::query()->latest('id')->first();
        $oldPaths = [];

        if ($existing) {
            foreach (self::MEDIA_FIELDS as $field) {
                if ($existing->{$field}) {
                    $oldPaths[$field] = $existing->{$field};
                }
            }
        }

        $record = DB::transaction(function () use ($existing, $data, $userId): BrandSetting {
            $payload = array_merge($this->getDefaults(), $data, [
                'updated_by' => $userId,
            ]);

            if ($existing) {
                $existing->update($payload);

                return $existing->fresh();
            }

            return BrandSetting::query()->create($payload);
        });

        // Clean up replaced files only after DB commit succeeds, and only if not referenced by another slot.
        $activePaths = [];
        foreach (self::MEDIA_FIELDS as $field) {
            if ($record->{$field}) {
                $activePaths[] = $record->{$field};
            }
        }

        foreach ($oldPaths as $oldPath) {
            if (! in_array($oldPath, $activePaths, true)) {
                $this->deleteStoredFile($oldPath);
            }
        }

        $this->clearCache();

        return $record;
    }

    public function resetMediaField(string $field, ?int $userId = null): void
    {
        if (! in_array($field, self::MEDIA_FIELDS, true)) {
            return;
        }

        $existing = BrandSetting::query()->latest('id')->first();
        if (! $existing || ! $existing->{$field}) {
            return;
        }

        $oldPath = $existing->{$field};

        $existing->update([
            $field => null,
            'updated_by' => $userId,
        ]);

        $stillReferenced = false;
        foreach (self::MEDIA_FIELDS as $otherField) {
            if ($otherField !== $field && $existing->{$otherField} === $oldPath) {
                $stillReferenced = true;
                break;
            }
        }

        if (! $stillReferenced) {
            $this->deleteStoredFile($oldPath);
        }

        $this->clearCache();
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function resolvePublicUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        try {
            $disk = Storage::disk(self::STORAGE_DISK);
            if (! $disk->exists($path)) {
                return null;
            }

            return $disk->url($path);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Reject SVG uploads containing active scripts, event handlers, or dangerous tags.
     *
     * @param  array<string, mixed>  $data
     */
    protected function validateUploadedSvgs(array $data): void
    {
        $disk = Storage::disk(self::STORAGE_DISK);

        foreach (self::MEDIA_FIELDS as $field) {
            $path = $data[$field] ?? null;
            if (! is_string($path) || $path === '' || ! str_ends_with(strtolower($path), '.svg')) {
                continue;
            }

            if (! $disk->exists($path)) {
                continue;
            }

            $contents = (string) $disk->get($path);
            if ($this->containsUnsafeSvgContent($contents)) {
                $this->deleteStoredFile($path);

                throw ValidationException::withMessages([
                    "data.{$field}" => 'File SVG mengandung elemen atau skrip yang tidak aman dan ditolak oleh sistem.',
                ]);
            }
        }
    }

    public function containsUnsafeSvgContent(string $svg): bool
    {
        $patterns = [
            '/<\s*script\b/i',
            '/<\s*foreignObject\b/i',
            '/<\s*iframe\b/i',
            '/<\s*object\b/i',
            '/<\s*embed\b/i',
            '/\bon[a-z]+\s*=/i',
            '/javascript\s*:/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $svg) === 1) {
                return true;
            }
        }

        return false;
    }

    protected function deleteStoredFile(?string $path): void
    {
        if (! $path) {
            return;
        }

        try {
            $disk = Storage::disk(self::STORAGE_DISK);
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        } catch (Throwable) {
            // Ignore storage deletion failures to avoid breaking brand updates.
        }
    }
}
