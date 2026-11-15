<x-filament-panels::page>
    <div class="space-y-4">
        <x-filament::section>
            <x-slot name="heading">
                Pengawasan & Rekonsiliasi Penyesuaian Lintas Unit
            </x-slot>
            <x-slot name="description">
                Pasangan item lintas unit yang unik dan konsisten otomatis berstatus <strong>Cocok Otomatis (Sah)</strong> tanpa persetujuan manual. Finance dapat memantau antrean unit lawan, konflik atribut, serta melakukan koreksi atau pembatalan disertai alasan dan histori audit.
            </x-slot>

            {{ $this->table }}
        </x-filament::section>
    </div>
</x-filament-panels::page>
