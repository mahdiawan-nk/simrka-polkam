<x-filament-panels::page>
    @php
        $detail = $this->detailData;
        $record = $detail['record'];
        $summary = $detail['summary'];
        $finance = $detail['finance_summary'];
        $rupiah = fn ($val) => 'Rp ' . number_format((float) ($val ?? 0), 0, ',', '.');
    @endphp

    <div class="space-y-6">
        {{-- Header Meta Card --}}
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="space-y-1">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400">
                        Nomor Pengajuan: <span class="font-mono font-semibold text-gray-900 dark:text-white">{{ $record->document_number }}</span>
                    </div>
                    <h2 class="text-lg font-bold text-gray-950 dark:text-white">{{ $record->activity_name }}</h2>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-600 dark:text-gray-300">
                        <span>Unit Kerja: <strong>{{ $record->department?->name ?? '-' }}</strong></span>
                        <span>Pemohon: <strong>{{ $record->creator?->name ?? '-' }}</strong></span>
                        <span>Tahun Anggaran: <strong>{{ $record->fiscal_year }}</strong></span>
                    </div>
                </div>
                <x-filament::badge size="lg" color="{{ $detail['status_color'] }}">
                    {{ $detail['status_label'] }}
                </x-filament::badge>
            </div>
        </div>

        {{-- B. Progress Workflow --}}
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="mb-4 text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                Status & Progress Workflow
            </h3>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-5">
                @foreach ($detail['workflow_steps'] as $step)
                    @php
                        $stepStyle = match ($step['state']) {
                            'completed' => 'border-emerald-200 bg-emerald-50/70 text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200',
                            'current' => 'border-blue-300 bg-blue-50 text-blue-900 ring-1 ring-blue-300 dark:border-blue-700 dark:bg-blue-950/40 dark:text-blue-200',
                            'rejected' => 'border-rose-300 bg-rose-50 text-rose-900 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-200',
                            default => 'border-gray-200 bg-gray-50/50 text-gray-500 dark:border-gray-800 dark:bg-gray-800/30 dark:text-gray-400',
                        };
                    @endphp
                    <div class="rounded-lg border p-3 text-xs {{ $stepStyle }}">
                        <div class="font-bold">{{ $loop->iteration }}. {{ $step['label'] }}</div>
                        <div class="mt-1 text-[11px]">{{ $step['actor'] ?? 'Menunggu' }}</div>
                        @if (! empty($step['timestamp']))
                            <div class="mt-0.5 text-[10px] opacity-80">{{ $step['timestamp'] }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- C. Ringkasan Pengajuan --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Dimohonkan</div>
                <div class="mt-1 font-mono text-xl font-bold text-gray-950 dark:text-white">{{ $rupiah($summary['total_requested']) }}</div>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50/40 p-4 shadow-sm dark:border-emerald-900/60 dark:bg-emerald-950/20">
                <div class="text-xs font-medium text-emerald-700 dark:text-emerald-300">Total Disetujui</div>
                <div class="mt-1 font-mono text-xl font-bold text-emerald-700 dark:text-emerald-300">{{ $rupiah($summary['total_approved']) }}</div>
            </div>
            <div class="rounded-xl border border-rose-200 bg-rose-50/40 p-4 shadow-sm dark:border-rose-900/60 dark:bg-rose-950/20">
                <div class="text-xs font-medium text-rose-700 dark:text-rose-300">Total Ditolak / Tidak Disetujui</div>
                <div class="mt-1 font-mono text-xl font-bold text-rose-700 dark:text-rose-300">{{ $rupiah($summary['total_not_approved']) }}</div>
            </div>
        </div>

        {{-- D. Informasi Kegiatan --}}
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900 overflow-hidden">
            <div class="border-b border-gray-200 bg-gray-50 px-5 py-3 dark:border-gray-800 dark:bg-gray-800/60">
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">Informasi Kegiatan & Sumber Anggaran</h3>
            </div>
            <dl class="grid grid-cols-1 gap-4 p-5 text-xs sm:grid-cols-2">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Unit Kerja / Departemen</dt>
                    <dd class="mt-0.5 font-semibold text-gray-900 dark:text-white">{{ $record->department?->name ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Nomor Surat / Nota</dt>
                    <dd class="mt-0.5 font-mono font-semibold text-gray-900 dark:text-white">{{ $record->document_number }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Nama Kegiatan / Keperluan</dt>
                    <dd class="mt-0.5 font-semibold text-gray-900 dark:text-white">{{ $record->activity_name }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Tanggal Pengajuan</dt>
                    <dd class="mt-0.5 font-semibold text-gray-900 dark:text-white">{{ $record->request_date?->format('d M Y') ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Waktu Pelaksanaan</dt>
                    <dd class="mt-0.5 font-semibold text-gray-900 dark:text-white">{{ $record->execution_date?->format('d M Y') ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Penanggung Jawab</dt>
                    <dd class="mt-0.5 font-semibold text-gray-900 dark:text-white">{{ $record->responsible_person ?: '-' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Jenis Pencairan</dt>
                    <dd class="mt-0.5 font-semibold text-gray-900 dark:text-white">{{ $record->disbursement_type ?: '-' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Tahun Anggaran & Periode</dt>
                    <dd class="mt-0.5 font-semibold text-gray-900 dark:text-white">
                        {{ $record->fiscal_year }} {{ $record->rkaHeader?->schedule?->period_range ? '(' . $record->rkaHeader->schedule->period_range . ')' : '' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Sumber Anggaran / RKA</dt>
                    <dd class="mt-0.5 font-semibold text-gray-900 dark:text-white">
                        {{ $record->rka_header_id ? 'RKA #' . $record->rka_header_id . ' (APPROVED)' : 'Pengajuan Historis / Manual' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Penerima Pembayaran</dt>
                    <dd class="mt-0.5 font-semibold text-gray-900 dark:text-white">
                        {{ $record->recipient_name ?: '-' }} {{ $record->bank_account_info ? '— ' . $record->bank_account_info : '' }}
                    </dd>
                </div>
            </dl>
        </div>

        {{-- E. Rincian Kebutuhan & Item Pengajuan --}}
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900 overflow-hidden">
            <div class="border-b border-gray-200 bg-gray-50 px-5 py-3 dark:border-gray-800 dark:bg-gray-800/60">
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">Rincian Kebutuhan & Hasil Verifikasi Item</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200 text-left text-xs dark:divide-gray-800">
                    <thead class="bg-gray-50/70 text-[11px] uppercase text-gray-500 dark:bg-gray-800/40 dark:text-gray-400">
                        <tr>
                            <th class="p-3 text-center">No</th>
                            <th class="p-3">Kode Rekening</th>
                            <th class="p-3">Nama Rekening</th>
                            <th class="p-3 min-w-[200px]">Uraian Item</th>
                            <th class="p-3 text-center">Vol Awal</th>
                            <th class="p-3 text-center">Satuan</th>
                            <th class="p-3 text-right">Harga Satuan</th>
                            <th class="p-3 text-right">Total Dimohonkan</th>
                            <th class="p-3 text-center">Vol Disetujui</th>
                            <th class="p-3 text-right">Total Disetujui</th>
                            <th class="p-3 text-center">Status</th>
                            <th class="p-3">Catatan Keuangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @foreach ($record->items as $idx => $item)
                            @php
                                $vStatus = $item->verification_status;
                                $badgeColor = match ($vStatus) {
                                    'approved' => 'success',
                                    'partial' => 'warning',
                                    'rejected' => 'danger',
                                    default => 'gray',
                                };
                                $badgeLabel = match ($vStatus) {
                                    'approved' => 'Disetujui',
                                    'partial' => 'Disetujui Sebagian',
                                    'rejected' => 'Ditolak',
                                    default => 'Pending',
                                };
                            @endphp
                            <tr class="hover:bg-gray-50/60 dark:hover:bg-gray-800/30">
                                <td class="p-3 text-center text-gray-500">{{ $idx + 1 }}</td>
                                <td class="p-3 font-mono font-semibold text-gray-900 dark:text-white">{{ $item->account?->code ?? $item->rka_account_code_snapshot ?? '-' }}</td>
                                <td class="p-3 text-gray-700 dark:text-gray-300">{{ $item->account?->name ?? $item->rka_account_name_snapshot ?? '-' }}</td>
                                <td class="p-3 font-medium text-gray-900 dark:text-white">{{ $item->description }}</td>
                                <td class="p-3 text-center font-mono">{{ number_format((float) $item->volume, 2, ',', '.') }}</td>
                                <td class="p-3 text-center">{{ $item->unit }}</td>
                                <td class="p-3 text-right font-mono">{{ $rupiah($item->unit_price) }}</td>
                                <td class="p-3 text-right font-mono font-semibold text-gray-900 dark:text-white">{{ $rupiah($item->total_price) }}</td>
                                <td class="p-3 text-center font-mono">{{ $item->approved_volume !== null ? number_format((float) $item->approved_volume, 2, ',', '.') : '—' }}</td>
                                <td class="p-3 text-right font-mono font-semibold text-emerald-700 dark:text-emerald-400">
                                    {{ $item->approved_price !== null ? $rupiah($item->approved_price) : $rupiah(0) }}
                                </td>
                                <td class="p-3 text-center">
                                    <x-filament::badge size="sm" color="{{ $badgeColor }}">{{ $badgeLabel }}</x-filament::badge>
                                </td>
                                <td class="p-3 text-gray-600 dark:text-gray-400">{{ $item->finance_note ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 font-bold dark:bg-gray-800/70">
                        <tr>
                            <td colspan="7" class="p-3 text-right uppercase">Total</td>
                            <td class="p-3 text-right font-mono">{{ $rupiah($summary['total_requested']) }}</td>
                            <td></td>
                            <td class="p-3 text-right font-mono text-emerald-700 dark:text-emerald-400">{{ $rupiah($summary['total_approved']) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- F. Rekap per Kode Rekening --}}
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900 overflow-hidden">
            <div class="border-b border-gray-200 bg-gray-50 px-5 py-3 dark:border-gray-800 dark:bg-gray-800/60">
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">Rekapitulasi per Kode Rekening</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200 text-left text-xs dark:divide-gray-800">
                    <thead class="bg-gray-50/70 text-[11px] uppercase text-gray-500 dark:bg-gray-800/40 dark:text-gray-400">
                        <tr>
                            <th class="p-3">Kode Rekening</th>
                            <th class="p-3">Nama Rekening</th>
                            <th class="p-3 text-center">Jumlah Item</th>
                            <th class="p-3 text-right">Total Dimohonkan</th>
                            <th class="p-3 text-right">Total Disetujui</th>
                            <th class="p-3 text-right">Selisih</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @foreach ($detail['account_recaps'] as $recap)
                            <tr>
                                <td class="p-3 font-mono font-semibold">{{ $recap['account_code'] }}</td>
                                <td class="p-3">{{ $recap['account_name'] }}</td>
                                <td class="p-3 text-center font-mono">{{ $recap['item_count'] }}</td>
                                <td class="p-3 text-right font-mono">{{ $rupiah($recap['total_requested']) }}</td>
                                <td class="p-3 text-right font-mono font-semibold text-emerald-700 dark:text-emerald-400">{{ $rupiah($recap['total_approved']) }}</td>
                                <td class="p-3 text-right font-mono text-rose-600 dark:text-rose-400">{{ $rupiah($recap['selisih']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- G & H. Hasil Verifikasi Keuangan & Riwayat Persetujuan --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 space-y-3 text-xs">
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">Hasil Verifikasi Bagian Keuangan</h3>
                <div class="grid grid-cols-2 gap-3">
                    <div><span class="text-gray-500">Petugas Keuangan:</span><div class="font-semibold">{{ $finance['verifier_name'] }}</div></div>
                    <div><span class="text-gray-500">Tanggal Verifikasi:</span><div class="font-semibold">{{ $finance['verified_at'] ?? 'Belum diverifikasi' }}</div></div>
                    <div><span class="text-gray-500">Keputusan:</span><div class="font-semibold">{{ $finance['decision'] }}</div></div>
                    <div><span class="text-gray-500">Total Disetujui:</span><div class="font-mono font-bold text-emerald-700 dark:text-emerald-400">{{ $rupiah($finance['total_approved']) }}</div></div>
                </div>
                <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800/60">
                    <div class="text-gray-500">Catatan Verifikasi Keuangan:</div>
                    <div class="mt-1 font-medium text-gray-900 dark:text-white">{{ $finance['note'] ?: 'Belum ada catatan verifikasi.' }}</div>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 space-y-3 text-xs">
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">Riwayat Persetujuan Berjenjang</h3>
                <div class="space-y-2.5">
                    @foreach ($detail['approval_history'] as $hist)
                        <div class="flex items-start justify-between gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                            <div>
                                <div class="font-semibold text-gray-900 dark:text-white">{{ $hist['role_title'] }}</div>
                                <div class="text-[11px] text-gray-500">{{ $hist['officer_name'] }}{{ $hist['decided_at'] ? ' • ' . $hist['decided_at'] : '' }}</div>
                                @if (! empty($hist['note']))
                                    <div class="mt-1 text-[11px] italic text-gray-600 dark:text-gray-300">"{{ $hist['note'] }}"</div>
                                @endif
                            </div>
                            <x-filament::badge size="sm" color="{{ $hist['action'] === 'approved' ? 'success' : ($hist['action'] === 'rejected' ? 'danger' : 'gray') }}">
                                {{ $hist['decision'] }}
                            </x-filament::badge>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- I & J. Riwayat Aktivitas & Lampiran --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 space-y-3 text-xs">
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">Riwayat Aktivitas</h3>
                <div class="space-y-2">
                    @foreach ($detail['activity_logs'] as $log)
                        <div class="border-l-2 border-blue-500 pl-3 py-1">
                            <div class="font-semibold text-gray-900 dark:text-white">{{ $log['activity'] }}</div>
                            <div class="text-[11px] text-gray-500">{{ $log['actor'] }} • {{ $log['time'] }}</div>
                            <div class="text-[11px] text-gray-600 dark:text-gray-300">{{ $log['description'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 space-y-3 text-xs">
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">Lampiran Dokumen Pendukung</h3>
                @forelse ($detail['attachments'] as $att)
                    <div class="flex items-center justify-between gap-2 rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                        <div>
                            <div class="font-semibold text-gray-900 dark:text-white">{{ $att['name'] }}</div>
                            <div class="text-[11px] text-gray-500">{{ $att['type'] }} • {{ $att['uploaded_at'] }} • {{ $att['uploader'] }}</div>
                        </div>
                        @if ($att['available'])
                            <a href="{{ $att['download_url'] }}" target="_blank" class="rounded-md bg-blue-50 px-2.5 py-1 text-[11px] font-semibold text-blue-700 hover:bg-blue-100 dark:bg-blue-900/30 dark:text-blue-300">
                                Unduh
                            </a>
                        @else
                            <span class="text-[11px] text-gray-400">Tidak tersedia</span>
                        @endif
                    </div>
                @empty
                    <div class="text-gray-400 italic">Tidak ada lampiran dokumen.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-filament-panels::page>
