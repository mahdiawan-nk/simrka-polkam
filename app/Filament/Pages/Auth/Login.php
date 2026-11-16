<?php

namespace App\Filament\Pages\Auth;

use App\Services\Branding\BrandSettingsService;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;

class Login extends BaseLogin
{
    protected static string $layout = 'filament-panels::components.layout.base';

    protected string $view = 'filament.pages.auth.login';

    public function getTitle(): string|Htmlable
    {
        return 'Masuk — '.app(BrandSettingsService::class)->getBrowserTitle();
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Masuk ke Akun';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Gunakan akun institusi Anda untuk mengakses sistem perencanaan dan anggaran.';
    }

    /**
     * @return array<string, mixed>
     */
    public function getBrandContext(): array
    {
        $branding = app(BrandSettingsService::class);

        return [
            'brand_name' => $branding->getBrandName(),
            'app_full_name' => $branding->getAppFullName(),
            'institution_name' => $branding->getInstitutionName(),
            'institution_short_name' => $branding->getInstitutionShortName(),
            'tagline' => $branding->getTagline(),
            'short_description' => $branding->getShortDescription(),
            'logo_url' => $branding->getLogoUrl('login'),
        ];
    }
}
