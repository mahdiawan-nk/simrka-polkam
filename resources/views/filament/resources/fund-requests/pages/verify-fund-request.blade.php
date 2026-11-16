<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Header Dokumen (Read Only) --}}
        <div
            class="bg-white dark:bg-gray-900 p-6 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm space-y-4">
            <div class="text-center border-b border-gray-100 dark:border-gray-800 pb-4">
                <h2 class="text-2xl font-bold uppercase tracking-tight text-gray-900 dark:text-white">Formulir Permohonan
                    Pencairan Dana</h2>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mt-1">Tahun Anggaran
                    {{ $record->fiscal_year }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="block text-xs font-medium uppercase tracking-wider text-gray-500">Nomor Surat /
                        Nota</span>
                    <p class="font-bold text-gray-900 dark:text-white mt-0.5">{{ $record->document_number }}</p>
                </div>
                <div>
                    <span class="block text-xs font-medium uppercase tracking-wider text-gray-500">Tanggal
                        Permohonan</span>
                    <p class="font-bold text-gray-900 dark:text-white mt-0.5">
                        {{ \Carbon\Carbon::parse($record->request_date)->translatedFormat('d F Y') }}</p>
                </div>
            </div>
        </div>

        {{-- Section A: KEGIATAN --}}
        <div
            class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
            <div class="bg-gray-50 dark:bg-gray-800/60 px-6 py-3 border-b border-gray-200 dark:border-gray-800">
                <h3 class="font-bold text-xs uppercase tracking-wider text-blue-600 dark:text-blue-400">A. Informasi
                    Kegiatan</h3>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="text-xs text-gray-500 uppercase font-medium">Unit Kerja / Departemen</span>
                    <p class="font-semibold text-gray-900 dark:text-white mt-0.5">{{ $record->department->name ?? '-' }}
                        ({{ $record->department->code ?? '-' }})</p>
                </div>
                <div>
                    <span class="text-xs text-gray-500 uppercase font-medium">Nama Kegiatan / Keperluan</span>
                    <p class="font-semibold text-gray-900 dark:text-white mt-0.5">{{ $record->activity_name }}</p>
                </div>
                <div>
                    <span class="text-xs text-gray-500 uppercase font-medium">Waktu Pelaksanaan</span>
                    <p class="font-semibold text-gray-900 dark:text-white mt-0.5">
                        {{ $record->execution_date ? \Carbon\Carbon::parse($record->execution_date)->translatedFormat('d F Y') : '-' }}
                    </p>
                </div>
                <div>
                    <span class="text-xs text-gray-500 uppercase font-medium">Penanggung Jawab</span>
                    <p class="font-semibold text-gray-900 dark:text-white mt-0.5">{{ $record->responsible_person }}</p>
                </div>
                <div class="md:col-span-2">
                    <span class="text-xs text-gray-500 uppercase font-medium">Jenis Pencairan</span>
                    <p class="font-semibold text-gray-900 dark:text-white mt-0.5">{{ $record->disbursement_type }}</p>
                </div>
            </div>
        </div>

        {{-- Section B: RINCIAN KEBUTUHAN (Read Only) --}}
        <div
            class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
            <div class="bg-gray-50 dark:bg-gray-800/60 px-6 py-3 border-b border-gray-200 dark:border-gray-800">
                <h3 class="font-bold text-xs uppercase tracking-wider text-blue-600 dark:text-blue-400">
                    B. Rincian Kebutuhan & Verifikasi Item
                </h3>
            </div>

            <div class="p-4 overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr
                            class="border-b border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 uppercase font-semibold bg-gray-50 dark:bg-gray-800/60 tracking-wider">
                            <th class="p-3 w-10 text-center">No</th>
                            <th class="p-3 w-48">Kode & Nama Akun</th>
                            <th class="p-3 w-64">Uraian</th>
                            <th class="p-3 text-center">Satuan</th>
                            <th class="p-3 text-right">Vol (Awal)</th>
                            <th class="p-3 text-right">Harga Satuan</th>
                            <th class="p-3 text-right w-36">Total Dimohonkan</th>
                            <th class="p-3 text-right bg-emerald-50/50 dark:bg-emerald-950/20 w-28">Vol (Setuju)</th>
                            <th class="p-3 text-right bg-emerald-50/50 dark:bg-emerald-950/20 w-36">Total Disetujui</th>
                            <th class="p-3 text-center w-28">Status</th>
                            <th class="p-3 min-w-[160px]">Catatan Keuangan</th>
                            <th class="p-3 text-center w-20">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @foreach ($this->record->items as $index => $item)
                            @php
                                $v = $itemVerifications[$item->id] ?? [];
                                $status = $v['verification_status'] ?? null;

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
                            <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-800/40 transition-colors">
                                <td class="p-3 text-center text-gray-500 font-mono">{{ $index + 1 }}</td>

                                {{-- Kode Akun & Nama Akun --}}
                                <td class="p-3">
                                    <div class="font-mono font-bold text-gray-900 dark:text-gray-100">
                                        {{ $item->account->code ?? '-' }}
                                    </div>
                                    <div class="text-[11px] text-gray-500 dark:text-gray-400">
                                        {{ $item->account->name ?? '-' }}
                                    </div>
                                </td>

                                {{-- Uraian --}}
                                <td class="p-3 font-medium text-gray-800 dark:text-gray-200">
                                    {{ $item->description }}
                                </td>

                                {{-- Satuan --}}
                                <td class="p-3 text-center text-gray-600 dark:text-gray-400">
                                    {{ $item->unit }}
                                </td>

                                {{-- Vol Awal --}}
                                <td class="p-3 text-right font-mono text-gray-700 dark:text-gray-300">
                                    {{ number_format($item->volume, 0, ',', '.') }}
                                </td>

                                {{-- Harga Satuan --}}
                                <td class="p-3 text-right font-mono text-gray-700 dark:text-gray-300">
                                    <span
                                        class="text-[10px] text-gray-400 mr-0.5">Rp</span>{{ number_format($item->unit_price, 0, ',', '.') }}
                                </td>

                                {{-- Total Dimohonkan --}}
                                <td class="p-3 text-right font-mono font-semibold text-gray-900 dark:text-gray-100">
                                    <span
                                        class="text-[10px] text-gray-400 mr-0.5">Rp</span>{{ number_format($item->total_price, 0, ',', '.') }}
                                </td>

                                {{-- Volume Disetujui --}}
                                <td
                                    class="p-3 text-right font-mono bg-emerald-50/30 dark:bg-emerald-950/10 text-emerald-700 dark:text-emerald-400">
                                    {{ isset($v['approved_volume']) && $v['approved_volume'] !== null ? number_format($v['approved_volume'], 2, ',', '.') : '-' }}
                                </td>

                                {{-- Total Disetujui --}}
                                <td
                                    class="p-3 text-right font-mono font-bold bg-emerald-50/30 dark:bg-emerald-950/10 text-emerald-700 dark:text-emerald-400">
                                    @if (isset($v['approved_price']) && $v['approved_price'] !== null)
                                        <span
                                            class="text-[10px] text-emerald-500 mr-0.5">Rp</span>{{ number_format($v['approved_price'], 0, ',', '.') }}
                                    @else
                                        <span class="text-gray-400 font-normal italic">-</span>
                                    @endif
                                </td>

                                {{-- Status Badge --}}
                                <td class="p-3 text-center">
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 text-[10px] font-bold rounded-full {{ $badgeClass }}">
                                        {{ $statusLabel }}
                                    </span>
                                </td>

                                {{-- Catatan Keuangan --}}
                                <td class="p-3 text-gray-600 dark:text-gray-400 italic text-[11px]">
                                    {{ !empty($v['finance_note']) ? $v['finance_note'] : '-' }}
                                </td>

                                {{-- Aksi --}}
                                <td class="p-3 text-center">
                                    @if ($this->canVerifyItems())
                                        <button type="button" wire:click="openApprovalModal({{ $item->id }})"
                                            class="inline-flex items-center px-2.5 py-1 text-xs font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800 dark:hover:bg-blue-900/50 rounded transition-colors shadow-xs">
                                            Edit
                                        </button>
                                    @else
                                        <span class="text-gray-400 font-normal italic text-[11px]">Read-only</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>

                    {{-- Footer Total --}}
                    <tfoot>
                        <tr
                            class="bg-gray-100/80 dark:bg-gray-800/80 font-bold border-t-2 border-gray-300 dark:border-gray-700">
                            <td colspan="6"
                                class="p-3 text-right uppercase tracking-wider text-gray-700 dark:text-gray-300 text-[11px]">
                                Total Keseluruhan:
                            </td>
                            <td class="p-3 text-right font-mono text-sm text-blue-700 dark:text-blue-400 font-black">
                                <span class="text-[10px] text-blue-500 font-normal">Rp</span>
                                {{ number_format($this->record->items->sum('total_price'), 0, ',', '.') }}
                            </td>
                            <td></td>
                            <td
                                class="p-3 text-right font-mono text-sm text-emerald-700 dark:text-emerald-400 font-black bg-emerald-100/40 dark:bg-emerald-950/30">
                                <span class="text-[10px] text-emerald-500 font-normal">Rp</span>
                                {{ number_format($this->approvedTotal, 0, ',', '.') }}
                            </td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- SECTION F: VERIFIKASI DAN PERSETUJUAN PER KODE (Diisi Bagian Keuangan) --}}
        @php
            /** @var \App\Models\User $currentUser */
            $currentUser = auth()->user();

            // Read-only jika user adalah Pemohon, Wadir BAKKU, atau Direktur
            // (Atau jika user TIDAK memiliki permission/role untuk verifikasi Ka BAKKU)
            $isReadOnly =
                $currentUser->hasAnyRole(['pemohon', 'wadir_keuangan', 'direktur', 'super_admin']) ||
                !$currentUser->can('Update:FundRequest');
        @endphp

        <div
            class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
            <div
                class="bg-blue-50 dark:bg-blue-950/40 px-6 py-3.5 border-b border-blue-100 dark:border-blue-900/50 flex justify-between items-center">
                <h3 class="font-bold text-xs uppercase tracking-wider text-blue-800 dark:text-blue-300">
                    F. VERIFIKASI DAN PERSETUJUAN PER KODE (diisi Bagian Keuangan)
                </h3>
                <span
                    class="text-xs px-2.5 py-0.5 rounded-full {{ $isReadOnly ? 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' : 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' }} font-medium">
                    {{ $isReadOnly ? 'Mode Lihat (Read-Only)' : 'Mode Verifikasi' }}
                </span>
            </div>

            <div class="p-4 overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr
                            class="bg-gray-100 dark:bg-gray-800 border-b border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 font-bold uppercase tracking-wider">
                            <th class="p-2.5 w-10 text-center border-r border-gray-200 dark:border-gray-700">No</th>
                            <th class="p-2.5 w-28 border-r border-gray-200 dark:border-gray-700">Kode Rekening</th>
                            <th class="p-2.5 min-w-[180px] border-r border-gray-200 dark:border-gray-700">Nama Rekening</th>
                            <th class="p-2.5 w-20 text-center border-r border-gray-200 dark:border-gray-700">Jml Item</th>
                            <th class="p-2.5 w-36 text-right border-r border-gray-200 dark:border-gray-700">Total Dimohonkan</th>
                            <th class="p-2.5 w-36 text-right border-r border-gray-200 dark:border-gray-700">Total Disetujui</th>
                            <th class="p-2.5 w-32 text-right border-r border-gray-200 dark:border-gray-700">Selisih</th>
                            <th class="p-2.5 w-36 text-right border-r border-gray-200 dark:border-gray-700">Sisa Pagu RKA</th>
                            <th class="p-2.5 w-40 text-center border-r border-gray-200 dark:border-gray-700">Status Agregat</th>
                            <th class="p-2.5 w-36 border-r border-gray-200 dark:border-gray-700">No. Bukti Register</th>
                            <th class="p-2.5 w-28 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse($verifications as $index => $v)
                            @php
                                $aggBadge = match ($v['status'] ?? '') {
                                    'Disetujui' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
                                    'Disetujui Sebagian' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
                                    'Ditolak' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
                                    default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
                                };
                            @endphp
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/40 transition">
                                <td class="p-2.5 text-center font-medium border-r border-gray-200 dark:border-gray-800">
                                    {{ $index + 1 }}
                                </td>
                                <td class="p-2.5 font-mono font-semibold border-r border-gray-200 dark:border-gray-800 text-gray-900 dark:text-white">
                                    {{ $v['account_code'] }}
                                </td>
                                <td class="p-2.5 border-r border-gray-200 dark:border-gray-800 text-gray-800 dark:text-gray-200">
                                    {{ $v['account_name'] ?? '-' }}
                                </td>
                                <td class="p-2.5 text-center font-mono border-r border-gray-200 dark:border-gray-800">
                                    {{ $v['item_count'] ?? 0 }}
                                </td>
                                <td class="p-2.5 text-right font-mono border-r border-gray-200 dark:border-gray-800 text-gray-800 dark:text-gray-200">
                                    Rp {{ number_format($v['total_requested'] ?? 0, 0, ',', '.') }}
                                </td>
                                <td class="p-2.5 text-right font-mono font-bold border-r border-gray-200 dark:border-gray-800 text-emerald-700 dark:text-emerald-400">
                                    Rp {{ number_format($v['amount_approved'] ?? 0, 0, ',', '.') }}
                                </td>
                                <td class="p-2.5 text-right font-mono border-r border-gray-200 dark:border-gray-800 text-rose-600 dark:text-rose-400">
                                    Rp {{ number_format($v['selisih'] ?? 0, 0, ',', '.') }}
                                </td>
                                <td class="p-2.5 text-right font-mono border-r border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-300">
                                    Rp {{ number_format($v['remaining_budget'] ?? 0, 0, ',', '.') }}
                                </td>
                                <td class="p-2.5 text-center border-r border-gray-200 dark:border-gray-800">
                                    <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-bold rounded-full {{ $aggBadge }}">
                                        {{ $v['status'] ?? 'Belum Selesai Diverifikasi' }}
                                    </span>
                                </td>
                                <td class="p-1.5 border-r border-gray-200 dark:border-gray-800">
                                    <input type="text"
                                        wire:model="verifications.{{ $index }}.register_proof_number"
                                        placeholder="No. Reg..." @disabled($isReadOnly)
                                        class="w-full rounded-md border-0 py-1.5 px-2 text-gray-900 dark:text-white bg-white dark:bg-gray-800 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-700 placeholder:text-gray-400 focus:ring-2 focus:ring-blue-600 text-xs transition disabled:bg-gray-100 dark:disabled:bg-gray-800/50 disabled:text-gray-500 disabled:ring-gray-200 dark:disabled:ring-gray-800 disabled:cursor-not-allowed" />
                                </td>
                                <td class="p-2 text-center">
                                    <button type="button"
                                        wire:click="openAccountDetail({{ $v['account_id'] }})"
                                        class="inline-flex items-center px-2.5 py-1 text-[11px] font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800 rounded transition-colors">
                                        Lihat Rincian
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="p-4 text-center text-gray-400 italic">Tidak ada akun untuk
                                    diverifikasi.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr
                            class="bg-gray-200/80 dark:bg-gray-800 font-bold border-t-2 border-gray-300 dark:border-gray-700">
                            <td colspan="4"
                                class="p-3 text-right uppercase tracking-wider text-gray-800 dark:text-gray-200">TOTAL REKAP:</td>
                            <td class="p-3 text-right font-mono text-sm text-blue-700 dark:text-blue-400 font-extrabold border-r border-gray-300 dark:border-gray-700">
                                Rp {{ number_format(collect($verifications)->sum('total_requested'), 0, ',', '.') }}
                            </td>
                            <td
                                class="p-3 text-right font-mono text-sm text-green-700 dark:text-green-400 font-extrabold border-r border-gray-300 dark:border-gray-700">
                                Rp {{ number_format($this->approvedTotal, 0, ',', '.') }}
                            </td>
                            <td class="p-3 text-right font-mono text-sm text-rose-600 dark:text-rose-400 font-extrabold border-r border-gray-300 dark:border-gray-700">
                                Rp {{ number_format(collect($verifications)->sum('selisih'), 0, ',', '.') }}
                            </td>
                            <td colspan="4"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Catatan Verifikasi Keuangan --}}
            {{-- Catatan Verifikasi Keuangan --}}
            <div class="p-4 border-t border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900/50">
                <label
                    class="block text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-1.5">
                    Catatan Verifikator / Alasan Ditolak
                </label>

                @if (strtolower($this->record->status) === 'rejected')
                    {{-- Tampilan Read-Only Log Note jika Status Rejected --}}
                    <div class="space-y-2">
                        @forelse (collect($this->record->approvals)->whereNotNull('note')->where('note', '!=', '') as $approval)
                            <div
                                class="p-3 rounded-lg border border-rose-200 dark:border-rose-900/50 bg-rose-50/50 dark:bg-rose-950/20 text-xs">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="font-bold text-rose-800 dark:text-rose-300 uppercase text-[10px]">
                                        {{ $approval->stage ?? 'Persetujuan' }} -
                                        {{ $approval->user->name ?? 'Verifikator' }}
                                    </span>
                                    <span class="text-[10px] text-gray-400 font-mono">
                                        {{ $approval->created_at ? $approval->created_at->format('d M Y, H:i') : '-' }}
                                    </span>
                                </div>
                                <p class="text-gray-700 dark:text-gray-300 italic">
                                    "{{ $approval->note }}"
                                </p>
                            </div>
                        @empty
                            <div
                                class="p-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-xs text-gray-400 italic">
                                Tidak ada catatan penolakan.
                            </div>
                        @endforelse
                    </div>
                @else
                    {{-- Textarea Input jika Status belum Rejected --}}
                    <textarea wire:model="verification_note" rows="2"
                        placeholder="Masukkan catatan verifikasi atau alasan jika ditolak..."
                        class="w-full rounded-lg border-0 py-2 px-3 text-gray-900 dark:text-white bg-white dark:bg-gray-800 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-700 placeholder:text-gray-400 focus:ring-2 focus:ring-blue-600 text-xs transition disabled:bg-gray-100 dark:disabled:bg-gray-800/50 disabled:text-gray-500 disabled:ring-gray-200 dark:disabled:ring-gray-800 disabled:cursor-not-allowed"
                        @disabled(!$this->canVerifyItems())></textarea>
                @endif
            </div>
        </div>

        {{-- Action Submit Buttons --}}
        @php
            /** @var \App\Models\User $currentUser */
            $currentUser = auth()->user();

            // Status terkini dari pengajuan dana
            $status = $this->record->status ?? '';

            // Logika Show / Hide berdasarkan Role dan Status Dokumen
            $showButtons = false;

            if ($currentUser->hasRole('super_admin')) {
                // Super Admin selalu memiliki akses aksi
                $showButtons = true;
            } elseif ($currentUser->hasRole('keuangan') && $status === 'submitted') {
                // Keuangan / Ka BAKKU hanya melihat tombol saat status 'submitted'
                $showButtons = true;
            } elseif ($currentUser->hasRole('wadir_keuangan') && $status === 'verified_kabakku') {
                // Wadir Keuangan hanya melihat tombol saat status 'verified_kabakku'
                $showButtons = true;
            } elseif ($currentUser->hasRole('direktur') && $status === 'verified_wadir_bakku') {
                // Direktur hanya melihat tombol saat status 'verified_wadir_bakku'
                $showButtons = true;
            }
        @endphp

        @if ($showButtons)
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" wire:click="$set('action_type', 'reject'); saveVerification()"
                    class="inline-flex items-center gap-x-2 rounded-lg bg-red-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-red-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 transition">
                    Tolak Pengajuan
                </button>
                <button type="button" wire:click="$set('action_type', 'approve'); saveVerification()"
                    class="inline-flex items-center gap-x-2 rounded-lg bg-green-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-green-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-600 transition">
                    Setujui & Simpan Verifikasi
                </button>
            </div>
        @endif

    </div>

    {{-- RIGHT-SIDE SLIDE-OVER VERIFIKASI ITEM --}}
    @if ($isModalOpen)
        @php
            $projectedApproved = $modal_verification_status === 'rejected' ? 0.0 : (float) ($modal_approved_price ?? 0);
            $projectedDifference = max(0.0, round($selectedItemRequestedPrice - $projectedApproved, 2));
            $projectedRemainingAfter = round($selectedItemRemainingBefore - $projectedApproved, 2);
            $currentBadgeColor = match ($selectedItemCurrentStatus) {
                'approved' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
                'partial' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
                'rejected' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
                default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
            };
        @endphp
        <div
            x-data="{
                dirty: @entangle('hasUnsavedModalChanges'),
                tryClose() {
                    if (this.dirty && !confirm('Terdapat perubahan verifikasi yang belum disimpan. Tutup panel ini?')) {
                        return;
                    }
                    $wire.closeModal();
                }
            }"
            x-on:keydown.escape.window="tryClose()"
            class="fixed inset-0 z-50 overflow-hidden"
            role="dialog"
            aria-modal="true"
            aria-labelledby="verify-item-slideover-title"
        >
            {{-- Backdrop --}}
            <div x-on:click="tryClose()" class="fixed inset-0 bg-gray-900/60 backdrop-blur-xs transition-opacity"></div>

            <div class="fixed inset-y-0 right-0 flex max-w-full pl-0 sm:pl-10">
                <div class="flex h-full w-screen max-w-full flex-col bg-white shadow-2xl dark:bg-gray-900 sm:max-w-[680px]">
                    {{-- A. Header Sticky --}}
                    <div class="shrink-0 border-b border-gray-200 bg-gray-50/90 px-6 py-4 dark:border-gray-800 dark:bg-gray-800/80">
                        <div class="flex items-start justify-between gap-4">
                            <div class="space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-mono text-xs font-semibold text-blue-700 dark:text-blue-400">
                                        {{ $selectedItemAccountCode }}
                                    </span>
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase {{ $currentBadgeColor }}">
                                        {{ $selectedItemCurrentStatus ? strtoupper($selectedItemCurrentStatus) : 'BELUM DIVERIFIKASI' }}
                                    </span>
                                </div>
                                <h3 id="verify-item-slideover-title" class="text-sm font-bold text-gray-900 dark:text-white">
                                    Verifikasi Item: {{ $selectedItemDescription }}
                                </h3>
                            </div>
                            <button
                                type="button"
                                x-on:click="tryClose()"
                                aria-label="Tutup panel verifikasi"
                                class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-200/70 hover:text-gray-700 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                            >
                                <x-heroicon-o-x-mark class="h-5 w-5" />
                            </button>
                        </div>
                    </div>

                    {{-- Scrollable Body --}}
                    <div class="flex-1 space-y-5 overflow-y-auto px-6 py-5 text-xs">
                        {{-- B. Ringkasan Item --}}
                        <div class="rounded-xl border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-800 dark:bg-gray-800/50">
                            <h4 class="mb-3 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Ringkasan Item Permohonan
                            </h4>
                            <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <dt class="text-gray-500 dark:text-gray-400">Nama Rekening</dt>
                                    <dd class="font-semibold text-gray-900 dark:text-white">{{ $selectedItemAccountName }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500 dark:text-gray-400">Unit Pemohon</dt>
                                    <dd class="font-semibold text-gray-900 dark:text-white">{{ $this->record->department?->name ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500 dark:text-gray-400">Program / Kegiatan RKA</dt>
                                    <dd class="text-gray-800 dark:text-gray-200">{{ $selectedItemProgramActivity ?: '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500 dark:text-gray-400">Sumber RKA</dt>
                                    <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $selectedItemRkaSource }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500 dark:text-gray-400">Volume & Satuan Dimohonkan</dt>
                                    <dd class="font-mono font-semibold text-gray-900 dark:text-white">
                                        {{ number_format($selectedItemRequestedVolume, 2, ',', '.') }} {{ $selectedItemUnit }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500 dark:text-gray-400">Harga Satuan (Read-only)</dt>
                                    <dd class="font-mono font-semibold text-gray-900 dark:text-white">
                                        Rp {{ number_format($selectedItemUnitPrice, 0, ',', '.') }}
                                    </dd>
                                </div>
                                <div class="sm:col-span-2 flex items-center justify-between border-t border-gray-200 pt-2.5 dark:border-gray-700">
                                    <span class="font-medium text-gray-600 dark:text-gray-300">Total Dimohonkan</span>
                                    <span class="font-mono text-sm font-bold text-blue-700 dark:text-blue-400">
                                        Rp {{ number_format($selectedItemRequestedPrice, 0, ',', '.') }}
                                    </span>
                                </div>
                            </dl>
                        </div>

                        {{-- C. Ringkasan Anggaran RKA --}}
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-4 dark:border-emerald-900/60 dark:bg-emerald-950/20">
                            <h4 class="mb-3 text-[11px] font-bold uppercase tracking-wider text-emerald-800 dark:text-emerald-300">
                                Ringkasan Anggaran Item RKA
                            </h4>
                            <dl class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                <div>
                                    <dt class="text-gray-500 dark:text-gray-400">Nilai Disetujui RKA</dt>
                                    <dd class="font-mono font-semibold text-gray-900 dark:text-white">
                                        Rp {{ number_format($selectedItemRkaApprovedTotal, 0, ',', '.') }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500 dark:text-gray-400">Penggunaan Lain</dt>
                                    <dd class="font-mono text-gray-700 dark:text-gray-300">
                                        Rp {{ number_format($selectedItemOtherUsed, 0, ',', '.') }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500 dark:text-gray-400">Sisa Sebelum</dt>
                                    <dd class="font-mono font-semibold text-emerald-700 dark:text-emerald-400">
                                        Rp {{ number_format($selectedItemRemainingBefore, 0, ',', '.') }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500 dark:text-gray-400">Selisih Tidak Disetujui</dt>
                                    <dd class="font-mono font-semibold text-rose-600 dark:text-rose-400">
                                        Rp {{ number_format($projectedDifference, 0, ',', '.') }}
                                    </dd>
                                </div>
                                <div class="col-span-2">
                                    <dt class="text-gray-500 dark:text-gray-400">Proyeksi Sisa Setelah Verifikasi</dt>
                                    <dd class="font-mono font-bold {{ $projectedRemainingAfter < 0 ? 'text-rose-600' : 'text-emerald-700 dark:text-emerald-400' }}">
                                        Rp {{ number_format($projectedRemainingAfter, 0, ',', '.') }}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        {{-- D. Form Verifikasi --}}
                        <div class="space-y-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                            <h4 class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Keputusan Verifikasi Keuangan
                            </h4>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block font-medium text-gray-700 dark:text-gray-300">
                                        Volume Disetujui (Maks: {{ number_format($selectedItemRequestedVolume, 2, ',', '.') }})
                                    </label>
                                    <x-filament::input.wrapper>
                                        <x-filament::input type="number" step="0.01" min="0"
                                            max="{{ $selectedItemRequestedVolume }}"
                                            wire:model.live="modal_approved_volume" placeholder="0.00" />
                                    </x-filament::input.wrapper>
                                    @error('modal_approved_volume')
                                        <span class="mt-1 block text-[11px] text-rose-600">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div>
                                    <label class="mb-1 block font-medium text-gray-700 dark:text-gray-300">
                                        Total Disetujui (Rp)
                                    </label>
                                    <x-filament::input.wrapper>
                                        <x-filament::input type="number" min="0"
                                            max="{{ $selectedItemRequestedPrice }}"
                                            wire:model.live="modal_approved_price" placeholder="0" />
                                    </x-filament::input.wrapper>
                                    @error('modal_approved_price')
                                        <span class="mt-1 block text-[11px] text-rose-600">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block font-medium text-gray-700 dark:text-gray-300">
                                    Status Verifikasi
                                </label>
                                <x-filament::input.wrapper>
                                    <x-filament::input.select wire:model.live="modal_verification_status">
                                        <option value="">-- Pilih Status --</option>
                                        <option value="approved">Disetujui (Approved)</option>
                                        <option value="partial">Disetujui Sebagian (Partial)</option>
                                        <option value="rejected">Ditolak (Rejected)</option>
                                    </x-filament::input.select>
                                </x-filament::input.wrapper>
                                @error('modal_verification_status')
                                    <span class="mt-1 block text-[11px] text-rose-600">{{ $message }}</span>
                                @enderror
                            </div>

                            <div>
                                <label class="mb-1 block font-medium text-gray-700 dark:text-gray-300">
                                    Catatan Keuangan
                                </label>
                                <textarea wire:model.live="modal_finance_note" rows="3" placeholder="Alasan penyesuaian/penolakan..."
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"></textarea>
                                @error('modal_finance_note')
                                    <span class="mt-1 block text-[11px] text-rose-600">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- E. Footer Sticky --}}
                    <div class="shrink-0 flex items-center justify-end gap-2 border-t border-gray-200 bg-gray-50 px-6 py-3.5 dark:border-gray-800 dark:bg-gray-800/80">
                        <button type="button" x-on:click="tryClose()"
                            wire:loading.attr="disabled" wire:target="saveItemApproval"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                            Batal
                        </button>
                        <button type="button" wire:click="saveItemApproval"
                            wire:loading.attr="disabled" wire:target="saveItemApproval"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                            <span wire:loading.remove wire:target="saveItemApproval">Simpan Verifikasi</span>
                            <span wire:loading wire:target="saveItemApproval">Menyimpan...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- SLIDE-OVER RINCIAN ITEM PER KODE REKENING (BAGIAN F) --}}
    @if ($detailAccountId !== null)
        @php
            $detailItems = $this->detailAccountItems;
            $firstDetail = $detailItems->first();
        @endphp
        <div
            x-data
            x-on:keydown.escape.window="$wire.closeAccountDetail()"
            class="fixed inset-0 z-50 overflow-hidden"
            role="dialog"
            aria-modal="true"
        >
            <div x-on:click="$wire.closeAccountDetail()" class="fixed inset-0 bg-gray-900/60 backdrop-blur-xs"></div>
            <div class="fixed inset-y-0 right-0 flex max-w-full pl-0 sm:pl-10">
                <div class="flex h-full w-screen max-w-full flex-col bg-white shadow-2xl dark:bg-gray-900 sm:max-w-[640px]">
                    <div class="shrink-0 flex items-center justify-between border-b border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-800 dark:bg-gray-800/80">
                        <div>
                            <div class="font-mono text-xs font-semibold text-blue-700 dark:text-blue-400">
                                {{ $firstDetail?->account?->code ?? '-' }}
                            </div>
                            <h3 class="text-sm font-bold text-gray-900 dark:text-white">
                                Rincian Item: {{ $firstDetail?->account?->name ?? '-' }}
                            </h3>
                        </div>
                        <button type="button" wire:click="closeAccountDetail" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700">
                            <x-heroicon-o-x-mark class="h-5 w-5" />
                        </button>
                    </div>
                    <div class="flex-1 space-y-3 overflow-y-auto px-6 py-5 text-xs">
                        @foreach ($detailItems as $dItem)
                            @php $dv = $itemVerifications[$dItem->id] ?? []; @endphp
                            <div class="rounded-xl border border-gray-200 p-3.5 dark:border-gray-800">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="font-semibold text-gray-900 dark:text-white">{{ $dItem->description }}</div>
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-bold uppercase dark:bg-gray-800">
                                        {{ $dv['verification_status'] ?? 'PENDING' }}
                                    </span>
                                </div>
                                <div class="mt-2 grid grid-cols-2 gap-2 text-[11px]">
                                    <div>Dimohonkan: <strong class="font-mono">Rp {{ number_format($dItem->total_price, 0, ',', '.') }}</strong></div>
                                    <div>Disetujui: <strong class="font-mono text-emerald-700 dark:text-emerald-400">Rp {{ number_format($dv['approved_price'] ?? 0, 0, ',', '.') }}</strong></div>
                                </div>
                                @if (! empty($dv['finance_note']))
                                    <div class="mt-1.5 text-[11px] italic text-gray-500">Catatan: {{ $dv['finance_note'] }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <div class="shrink-0 flex justify-end border-t border-gray-200 bg-gray-50 px-6 py-3 dark:border-gray-800 dark:bg-gray-800/80">
                        <button type="button" wire:click="closeAccountDetail" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
