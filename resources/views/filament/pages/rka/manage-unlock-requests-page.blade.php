<x-filament-panels::page>
    <div class="space-y-4">
        <x-filament::section>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Daftar permintaan pembukaan kunci RKA yang telah disetujui (<strong>APPROVED</strong>).
                Tim Keuangan (<code>keuangan</code> / <code>wadir_keuangan</code>) dapat menyetujui untuk memberikan hak import ulang satu kali atau menolak permintaan.
                Status RKA tidak akan pernah kembali menjadi <strong>DRAFT</strong>.
            </p>
        </x-filament::section>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
