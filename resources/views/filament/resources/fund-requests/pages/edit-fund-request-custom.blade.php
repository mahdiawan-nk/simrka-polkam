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

        {{-- Section B: RINCIAN KEBUTUHAN --}}
        <div
            class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
            {{-- Header --}}
            <div
                class="bg-gray-50 dark:bg-gray-800/60 px-6 py-3 border-b border-gray-200 dark:border-gray-800 flex justify-between items-center">
                <div>
                    <h3 class="font-bold text-xs uppercase tracking-wider text-blue-600 dark:text-blue-400">
                        B. Rincian Kebutuhan & Hasil Verifikasi
                    </h3>
                    <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                        Silakan sesuaikan volume/harga berdasarkan catatan verifikasi keuangan di bawah.
                    </p>
                </div>
                <button type="button" wire:click="addItemRow"
                    class="inline-flex items-center gap-x-1.5 rounded-md bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 transition">
                    + Tambah Baris
                </button>
            </div>

            <div class="p-4 overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr
                            class="border-b border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 uppercase tracking-wider font-semibold">
                            <th class="p-2.5 w-10 text-center">No</th>
                            <th class="p-2.5 w-56">Kode Akun</th>
                            <th class="p-2.5">Uraian</th>
                            <th class="p-2.5 w-20 text-center">Vol</th>
                            <th class="p-2.5 w-24">Satuan</th>
                            <th class="p-2.5 w-32 text-right">Harga Satuan</th>
                            <th class="p-2.5 w-32 text-right">Jumlah Dimohonkan</th>
                            {{-- Kolom Verifikasi Keuangan --}}
                            <th
                                class="p-2.5 w-20 text-center bg-emerald-50/50 dark:bg-emerald-950/20 text-emerald-700 dark:text-emerald-400">
                                Vol Setuju</th>
                            <th
                                class="p-2.5 w-32 text-right bg-emerald-50/50 dark:bg-emerald-950/20 text-emerald-700 dark:text-emerald-400">
                                Total Setuju</th>
                            <th class="p-2.5 w-24 text-center">Status</th>
                            <th class="p-2.5 min-w-[140px]">Catatan Keuangan</th>
                            <th class="p-2.5 w-12 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @foreach ($items as $index => $item)
                            @php
                                $status = $item['verification_status'] ?? null;

                                $badgeClass = match ($status) {
                                    'approved'
                                        => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800',
                                    'partial'
                                        => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800',
                                    'rejected'
                                        => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300 border border-rose-200 dark:border-rose-800',
                                    default
                                        => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400 border border-gray-200 dark:border-gray-700',
                                };

                                $statusLabel = match ($status) {
                                    'approved' => 'Approved',
                                    'partial' => 'Partial',
                                    'rejected' => 'Rejected',
                                    default => 'Pending',
                                };
                            @endphp
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/40 transition">
                                <td class="p-2 text-center font-medium text-gray-500 dark:text-gray-400 font-mono">
                                    {{ $index + 1 }}
                                </td>

                                {{-- Pilih Akun --}}
                                <td class="p-1.5">
                                    <select wire:model.live="items.{{ $index }}.account_id"
                                        class="w-full rounded-md border-0 py-1.5 px-2 text-gray-900 dark:text-white bg-white dark:bg-gray-800 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-700 focus:ring-2 focus:ring-inset focus:ring-blue-600 text-xs transition">
                                        <option value="">-- Pilih Akun --</option>
                                        @foreach (\App\Models\Account::all() as $acc)
                                            <option value="{{ $acc->id }}">{{ $acc->code }} -
                                                {{ $acc->name }}</option>
                                        @endforeach
                                    </select>
                                </td>

                                {{-- Uraian --}}
                                <td class="p-1.5">
                                    <input type="text" wire:model="items.{{ $index }}.description"
                                        placeholder="Deskripsi kebutuhan..."
                                        class="w-full rounded-md border-0 py-1.5 px-2 text-gray-900 dark:text-white bg-white dark:bg-gray-800 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-700 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 text-xs transition" />
                                </td>

                                {{-- Volume --}}
                                <td class="p-1.5">
                                    <input type="number" step="0.01"
                                        wire:model.live="items.{{ $index }}.volume"
                                        class="w-full text-center rounded-md border-0 py-1.5 px-1.5 text-gray-900 dark:text-white bg-white dark:bg-gray-800 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-700 focus:ring-2 focus:ring-inset focus:ring-blue-600 text-xs font-mono transition" />
                                </td>

                                {{-- Satuan --}}
                                <td class="p-1.5">
                                    <input type="text" wire:model="items.{{ $index }}.unit"
                                        placeholder="Pcs/Ls"
                                        class="w-full rounded-md border-0 py-1.5 px-2 text-gray-900 dark:text-white bg-white dark:bg-gray-800 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-700 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 text-xs transition" />
                                </td>

                                {{-- Harga Satuan --}}
                                <td class="p-1.5">
                                    <input type="number" step="100"
                                        wire:model.live="items.{{ $index }}.unit_price"
                                        class="w-full text-right rounded-md border-0 py-1.5 px-2 text-gray-900 dark:text-white bg-white dark:bg-gray-800 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-700 focus:ring-2 focus:ring-inset focus:ring-blue-600 text-xs font-mono transition" />
                                </td>

                                {{-- Total Dimohonkan --}}
                                <td class="p-2 text-right font-semibold text-gray-900 dark:text-white font-mono">
                                    <span
                                        class="text-[10px] text-gray-400 mr-0.5">Rp</span>{{ number_format($item['total_price'] ?? 0, 0, ',', '.') }}
                                </td>

                                {{-- Hasil Verifikasi: Volume Approved --}}
                                <td
                                    class="p-2 text-center font-mono font-medium bg-emerald-50/30 dark:bg-emerald-950/10 text-emerald-700 dark:text-emerald-400">
                                    {{ isset($item['approved_volume']) && $item['approved_volume'] !== null ? number_format($item['approved_volume'], 2, ',', '.') : '-' }}
                                </td>

                                {{-- Hasil Verifikasi: Price Approved --}}
                                <td
                                    class="p-2 text-right font-mono font-bold bg-emerald-50/30 dark:bg-emerald-950/10 text-emerald-700 dark:text-emerald-400">
                                    @if (isset($item['approved_price']) && $item['approved_price'] !== null)
                                        <span
                                            class="text-[10px] text-emerald-500 mr-0.5">Rp</span>{{ number_format($item['approved_price'], 0, ',', '.') }}
                                    @else
                                        <span class="text-gray-400 font-normal italic">-</span>
                                    @endif
                                </td>

                                {{-- Status Verification --}}
                                <td class="p-2 text-center">
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 text-[10px] font-bold rounded-full {{ $badgeClass }}">
                                        {{ $statusLabel }}
                                    </span>
                                </td>

                                {{-- Catatan Keuangan --}}
                                <td class="p-2 text-gray-600 dark:text-gray-400 italic text-[11px]">
                                    {{ !empty($item['finance_note']) ? $item['finance_note'] : '-' }}
                                </td>

                                {{-- Aksi Hapus --}}
                                <td class="p-2 text-center">
                                    <button type="button" wire:click="removeItemRow({{ $index }})"
                                        class="p-1 text-red-600 hover:text-red-800 hover:bg-red-50 dark:hover:bg-red-900/30 rounded transition"
                                        title="Hapus Baris">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>

                    {{-- Footer Total --}}
                    <tfoot>
                        <tr
                            class="bg-gray-50/80 dark:bg-gray-800/50 font-bold border-t border-gray-200 dark:border-gray-800">
                            <td colspan="6"
                                class="p-3 text-right uppercase tracking-wider text-gray-600 dark:text-gray-400 text-[11px]">
                                Total Dimohonkan:
                            </td>
                            <td
                                class="p-3 text-right text-sm text-blue-600 dark:text-blue-400 font-extrabold font-mono">
                                <span class="text-[10px] text-blue-400 font-normal">Rp</span>
                                {{ number_format($this->totalRequested, 0, ',', '.') }}
                            </td>
                            <td
                                class="p-3 text-right uppercase tracking-wider text-gray-600 dark:text-gray-400 text-[11px] bg-emerald-50/30 dark:bg-emerald-950/10">
                                Total Disetujui:
                            </td>
                            <td
                                class="p-3 text-right text-sm text-emerald-600 dark:text-emerald-400 font-extrabold font-mono bg-emerald-50/30 dark:bg-emerald-950/10">
                                <span class="text-[10px] text-emerald-500 font-normal">Rp</span>
                                {{ number_format(collect($items)->whereIn('verification_status', ['approved', 'partial'])->sum('approved_price'),0,',','.') }}
                            </td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        {{-- Section Log Approval & Catatan Revisi --}}
        @if ($this->record->approvals && $this->record->approvals->count() > 0)
            <div
                class="mb-6 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
                {{-- Header Section --}}
                <div
                    class="bg-amber-50/60 dark:bg-amber-950/20 px-6 py-3.5 border-b border-amber-200/60 dark:border-amber-800/40 flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        <h3 class="font-bold text-xs uppercase tracking-wider text-amber-800 dark:text-amber-300">
                            Riwayat Persetujuan & Catatan Revisi
                        </h3>
                    </div>
                    <span class="text-[11px] font-medium text-amber-700 dark:text-amber-400">
                        {{ $this->record->approvals->count() }} Tahapan Terlibat
                    </span>
                </div>

                {{-- Isi Log / Timeline --}}
                <div class="p-5">
                    <div class="space-y-4">
                        @foreach ($this->record->approvals->sortByDesc('created_at') as $log)
                            @php
                                $action = strtolower($log->action ?? '');

                                $actionColor = match ($action) {
                                    'approved' => 'bg-emerald-500',
                                    'rejected' => 'bg-rose-500',
                                    default => 'bg-amber-500',
                                };

                                $badgeColor = match ($action) {
                                    'approved'
                                        => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800',
                                    'rejected'
                                        => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300 border border-rose-200 dark:border-rose-800',
                                    default
                                        => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800',
                                };

                                $actionLabel = match ($action) {
                                    'approved' => 'Disetujui',
                                    'rejected' => 'Perlu Revisi / Ditolak',
                                    default => ucfirst($log->action),
                                };
                            @endphp

                            <div
                                class="relative pl-6 pb-4 border-l-2 border-gray-200 dark:border-gray-800 last:border-l-0 last:pb-0">
                                {{-- Icon Status Dot --}}
                                <div
                                    class="absolute -left-[9px] top-0 w-4 h-4 rounded-full {{ $actionColor }} border-2 border-white dark:border-gray-900">
                                </div>

                                <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="font-bold text-xs text-gray-900 dark:text-gray-100 uppercase tracking-wider">
                                            {{ $log->stage ?? 'Verifikator' }}
                                        </span>
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 text-[10px] font-bold rounded-full {{ $badgeColor }}">
                                            {{ $actionLabel }}
                                        </span>
                                    </div>
                                    <span class="text-[11px] text-gray-400 font-mono">
                                        {{ $log->created_at ? $log->created_at->format('d M Y, H:i') : '-' }}
                                    </span>
                                </div>

                                <div class="text-xs text-gray-600 dark:text-gray-400 mb-2">
                                    Oleh: <span
                                        class="font-semibold text-gray-800 dark:text-gray-200">{{ $log->user->name ?? 'Sistem' }}</span>
                                    @if ($log->approved_amount > 0)
                                        <span class="mx-1">•</span> Total Disetujui: <span
                                            class="font-bold font-mono text-emerald-600 dark:text-emerald-400">Rp
                                            {{ number_format($log->approved_amount, 0, ',', '.') }}</span>
                                    @endif
                                </div>

                                {{-- Catatan Verifikator --}}
                                @if (!empty($log->note))
                                    <div
                                        class="p-3 bg-amber-50/70 dark:bg-amber-950/30 rounded-lg border border-amber-200/80 dark:border-amber-900/40 text-xs">
                                        <span
                                            class="block text-[10px] font-bold uppercase tracking-wider text-amber-800 dark:text-amber-400 mb-0.5">
                                            Catatan Revisi / Alasan:
                                        </span>
                                        <p class="text-gray-800 dark:text-gray-200 italic leading-relaxed">
                                            "{{ $log->note }}"
                                        </p>
                                    </div>
                                @else
                                    <div class="text-[11px] text-gray-400 italic">
                                        Tidak ada catatan tertulis.
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- Section C: REKAP PER KODE AKUN --}}
        <div
            class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
            <div class="bg-gray-50 dark:bg-gray-800/60 px-6 py-3.5 border-b border-gray-200 dark:border-gray-800">
                <h3 class="font-bold text-xs uppercase tracking-wider text-blue-600 dark:text-blue-400">
                    C. Rekap Per Kode Akun (Otomatis)
                </h3>
            </div>
            <div class="p-4 overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr
                            class="border-b border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 uppercase tracking-wider font-semibold">
                            <th class="p-2.5 w-10 text-center">No</th>
                            <th class="p-2.5 w-28">Kode</th>
                            <th class="p-2.5">Nama Akun</th>
                            <th class="p-2.5">Catatan</th>
                            <th class="p-2.5 w-32 text-right">Dimohonkan</th>
                            <th class="p-2.5 w-32 text-right text-emerald-600 dark:text-emerald-400">Disetujui</th>
                            <th class="p-2.5 w-32 text-right text-rose-600 dark:text-rose-400">Ditolak</th>
                            <th class="p-2.5 w-32 text-right">Pagu Unit</th>
                            <th class="p-2.5 w-28 text-center">Status Pagu</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse($this->accountSummaries as $i => $summary)
                            @php
                                // Menentukan apakah akumulasi yang disetujui/dimohonkan melebihi pagu
                                $isExceeded = $summary['total_requested'] > $summary['pagu_unit'];
                            @endphp
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/40 transition">
                                <td class="p-2.5 text-center text-gray-500 font-mono">{{ $i + 1 }}</td>
                                <td class="p-2.5 font-mono font-bold text-gray-900 dark:text-white">
                                    {{ $summary['account_code'] }}
                                </td>
                                <td class="p-2.5 text-gray-800 dark:text-gray-200 font-medium">
                                    {{ $summary['account_name'] }}
                                </td>
                                <td class="p-2.5 text-rose-500 italic">
                                    {{ $summary['note'] ?: '-' }}
                                </td>

                                {{-- Total Dimohonkan --}}
                                <td class="p-2.5 text-right font-mono font-semibold text-gray-900 dark:text-white">
                                    <span
                                        class="text-[10px] text-gray-400 mr-0.5">Rp</span>{{ number_format($summary['total_requested'], 0, ',', '.') }}
                                </td>

                                {{-- Total Disetujui (Approved + Partial) --}}
                                <td
                                    class="p-2.5 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50/20 dark:bg-emerald-950/10">
                                    <span
                                        class="text-[10px] text-emerald-500 mr-0.5">Rp</span>{{ number_format($summary['total_approved'], 0, ',', '.') }}
                                </td>

                                {{-- Total Ditolak (Rejected) --}}
                                <td
                                    class="p-2.5 text-right font-mono font-bold text-rose-600 dark:text-rose-400 bg-rose-50/20 dark:bg-rose-950/10">
                                    <span
                                        class="text-[10px] text-rose-500 mr-0.5">Rp</span>{{ number_format($summary['total_rejected'], 0, ',', '.') }}
                                </td>

                                {{-- Pagu Unit --}}
                                <td class="p-2.5 text-right font-mono text-gray-600 dark:text-gray-400">
                                    <span
                                        class="text-[10px] text-gray-400 mr-0.5">Rp</span>{{ number_format($summary['pagu_unit'], 0, ',', '.') }}
                                </td>

                                {{-- Status Pagu --}}
                                <td class="p-2.5 text-center">
                                    @if ($isExceeded)
                                        <span
                                            class="inline-flex items-center rounded-md bg-rose-50 dark:bg-rose-900/30 px-2 py-0.5 text-[11px] font-bold text-rose-700 dark:text-rose-400 ring-1 ring-inset ring-rose-600/20">
                                            Pagu Melampaui
                                        </span>
                                    @else
                                        <span
                                            class="inline-flex items-center rounded-md bg-emerald-50 dark:bg-emerald-900/30 px-2 py-0.5 text-[11px] font-bold text-emerald-700 dark:text-emerald-400 ring-1 ring-inset ring-emerald-600/20">
                                            Sesuai Pagu
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="p-6 text-center text-gray-400 italic">
                                    Pilih kode akun pada Rincian Kebutuhan di atas untuk melihat rekapitulasi.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

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
