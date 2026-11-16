<x-filament-panels::page>
    <form wire:submit.prevent="save" class="space-y-6">

        {{-- Header Dokumen --}}
        <div
            class="bg-white dark:bg-gray-900 p-6 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm space-y-6">
            <div class="text-center border-b border-gray-100 dark:border-gray-800 pb-4">
                <h2 class="text-2xl font-bold uppercase tracking-tight text-gray-900 dark:text-white">Formulir Permohonan
                    Pencairan Dana</h2>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mt-1">Tahun Anggaran {{ $fiscal_year }}
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Nomor Surat / Nota <span class="text-red-500">*</span>
                    </label>
                    <input type="text" wire:model="document_number" placeholder="Contoh: 013/PK.3/NOT/ICT/10.2026"
                        class="w-full rounded-lg border-0 py-2.5 px-3.5 text-gray-900 dark:text-white bg-white dark:bg-gray-800 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-700 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 text-sm transition"
                        required />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Tanggal Permohonan <span class="text-red-500">*</span>
                    </label>
                    <x-filament::input.wrapper>
                        <x-filament::input type="date" readonly wire:model="request_date" />
                    </x-filament::input.wrapper>
                </div>
            </div>
        </div>

        {{-- Section A: KEGIATAN --}}
        <div
            class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
            <div
                class="bg-gray-50 dark:bg-gray-800/60 px-6 py-3.5 border-b border-gray-200 dark:border-gray-800 flex items-center">
                <h3 class="font-bold text-xs uppercase tracking-wider text-blue-600 dark:text-blue-400">A. Informasi
                    Kegiatan</h3>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Unit Kerja / Departemen <span class="text-red-500">*</span>
                    </label>
                    <select wire:model.live="department_id" disabled="{{ $disableSelectDepartment }}"
                        class="w-full rounded-lg border-0 py-2.5 px-3.5 text-gray-900 dark:text-white bg-white dark:bg-gray-800 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-700 focus:ring-2 focus:ring-inset focus:ring-blue-600 text-sm transition"
                        required>
                        <option value="">-- Pilih Unit Kerja --</option>
                        @foreach (\App\Models\Department::all() as $dept)
                            <option value="{{ $dept->id }}">{{ $dept->name }} ({{ $dept->code }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Nama Kegiatan / Keperluan <span class="text-red-500">*</span>
                    </label>
                    <input type="text" wire:model="activity_name"
                        class="w-full rounded-lg border-0 py-2.5 px-3.5 text-gray-900 dark:text-white bg-white dark:bg-gray-800 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-700 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 text-sm transition"
                        required />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Waktu
                        Pelaksanaan</label>
                    <input type="date" readonly wire:model="execution_date"
                        class="w-full rounded-lg border-0 py-2.5 px-3.5 text-gray-900 dark:text-white bg-white dark:bg-gray-800 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-700 focus:ring-2 focus:ring-inset focus:ring-blue-600 text-sm transition" />
                    <small class="text-gray-500 dark:text-red-400 text-xs">Tanggal pelaksanaan kegiatan atau penggunaan
                        dana 14 hari setelah tanggal permohonan.</small>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Penanggung Jawab <span class="text-red-500">*</span>
                    </label>
                    <input type="text" wire:model="responsible_person"
                        class="w-full rounded-lg border-0 py-2.5 px-3.5 text-gray-900 dark:text-white bg-white dark:bg-gray-800 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-700 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 text-sm transition"
                        required />
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Jenis
                        Pencairan</label>
                    <select wire:model="disbursement_type"
                        class="w-full rounded-lg border-0 py-2.5 px-3.5 text-gray-900 dark:text-white bg-white dark:bg-gray-800 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-700 focus:ring-2 focus:ring-inset focus:ring-blue-600 text-sm transition">
                        <option value="transfer">Transfer</option>
                        <option value="tunai">Tunai</option>
                    </select>
                </div>
            </div>
        </div>

        @include('filament.resources.fund-requests.partials.rka-items-and-summary')

        {{-- Section D: PEMBAYARAN KEPADA --}}
        <div
            class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
            <div class="bg-gray-50 dark:bg-gray-800/60 px-6 py-3.5 border-b border-gray-200 dark:border-gray-800">
                <h3 class="font-bold text-xs uppercase tracking-wider text-blue-600 dark:text-blue-400">D. Pembayaran
                    Kepada</h3>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Nama Penerima <span class="text-red-500">*</span>
                    </label>
                    <input type="text" wire:model="recipient_name"
                        class="w-full rounded-lg border-0 py-2.5 px-3.5 text-gray-900 dark:text-white bg-white dark:bg-gray-800 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-700 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 text-sm transition"
                        required />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Bank / No. Rekening <span class="text-gray-400 font-normal">(Kosongkan jika tunai)</span>
                    </label>
                    <input type="text" wire:model="bank_account_info"
                        placeholder="Contoh: Bank Mandiri 10800xxxxxxxx a.n ..."
                        class="w-full rounded-lg border-0 py-2.5 px-3.5 text-gray-900 dark:text-white bg-white dark:bg-gray-800 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-700 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 text-sm transition" />
                </div>
            </div>
        </div>

        {{-- Section E: LAMPIRAN --}}
        <div
            class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
            <div class="bg-gray-50 dark:bg-gray-800/60 px-6 py-3.5 border-b border-gray-200 dark:border-gray-800">
                <h3 class="font-bold text-xs uppercase tracking-wider text-blue-600 dark:text-blue-400">E. Kelengkapan
                    Lampiran</h3>
            </div>
            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                @php
                    $attachmentOptions = [
                        'tor_kak' => 'TOR / KAK kegiatan',
                        'penawaran_nota' => 'Penawaran harga / nota / kuitansi',
                        'rab_rinci' => 'RAB rinci',
                        'daftar_hadir' => 'Daftar hadir / undangan / surat tugas',
                        'sk_panitia' => 'SK panitia / tim (untuk honor)',
                        'foto_dokumentasi' => 'Foto / dokumentasi (penggantian)',
                    ];
                @endphp

                @foreach ($attachmentOptions as $value => $label)
                    <label
                        class="relative flex items-center p-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50/40 dark:bg-gray-800/30 hover:bg-gray-100 dark:hover:bg-gray-800/60 cursor-pointer transition">
                        <input type="checkbox" wire:model="attachments" value="{{ $value }}"
                            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-600 dark:bg-gray-700 dark:border-gray-600" />
                        <span
                            class="ml-3 text-xs font-medium text-gray-700 dark:text-gray-300">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        {{-- Action Button --}}
        <div class="flex justify-end pt-2">
            <button type="submit"
                class="inline-flex items-center gap-x-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 transition">
                <span>Simpan & Ajukan Permohonan</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M14 5l7 7m0 0l-7 7m7-7H3" />
                </svg>
            </button>
        </div>

    </form>
</x-filament-panels::page>
