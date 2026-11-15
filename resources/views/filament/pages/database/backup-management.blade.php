
<x-filament-panels::page>
    @php
        $stats = $this->getStats();
    @endphp

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">

        <x-filament::section>
            <div class="flex items-center gap-3">
                <x-filament::icon
                    icon="heroicon-o-archive-box"
                    class="h-8 w-8 text-gray-500"
                />
                <div>
                    <div class="text-sm text-gray-500">Total Backup</div>
                    <div class="text-2xl font-bold">
                        {{ number_format($stats['total']) }}
                    </div>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="flex items-center gap-3">
                <x-filament::icon
                    icon="heroicon-o-check-circle"
                    class="h-8 w-8 text-success-500"
                />
                <div>
                    <div class="text-sm text-gray-500">Berhasil</div>
                    <div class="text-2xl font-bold">
                        {{ number_format($stats['success']) }}
                    </div>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="flex items-center gap-3">
                <x-filament::icon
                    icon="heroicon-o-arrow-path"
                    class="h-8 w-8 text-info-500"
                />
                <div>
                    <div class="text-sm text-gray-500">Diproses</div>
                    <div class="text-2xl font-bold">
                        {{ number_format($stats['processing']) }}
                    </div>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="flex items-center gap-3">
                <x-filament::icon
                    icon="heroicon-o-exclamation-triangle"
                    class="h-8 w-8 text-danger-500"
                />
                <div>
                    <div class="text-sm text-gray-500">Gagal</div>
                    <div class="text-2xl font-bold">
                        {{ number_format($stats['failed']) }}
                    </div>
                </div>
            </div>
        </x-filament::section>

    </div>

    <div class="mt-6">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
