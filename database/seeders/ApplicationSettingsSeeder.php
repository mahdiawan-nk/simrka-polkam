<?php

namespace Database\Seeders;

use App\Models\BrandSetting;
use App\Models\RkaSetting;
use App\Services\Branding\BrandSettingsService;
use Illuminate\Database\Seeder;

class ApplicationSettingsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Inisialisasi Identitas Aplikasi default hanya jika tabel masih kosong
        if (! BrandSetting::query()->exists()) {
            $defaults = app(BrandSettingsService::class)->getDefaults();
            BrandSetting::query()->create($defaults);
            app(BrandSettingsService::class)->clearCache();
        }

        // 2. Inisialisasi RKA Setting default jika belum tersedia
        if (! RkaSetting::query()->exists()) {
            $setting = new RkaSetting;

            RkaSetting::query()->create([
                'setting_name' => 'Default RKA Politeknik Kampar',
                'director_name' => 'Direktur Politeknik Kampar',
                'director_nrp' => '-',
                'foundation_head_name' => 'Ketua Yayasan',
                'signing_place' => 'Bangkinang',
                'pdf_header_config' => $setting->getPdfHeaderConfig(),
                'pdf_footer_config' => $setting->getPdfFooterConfig(),
                'signing_config' => [],
            ]);
        }
    }
}
