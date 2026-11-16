<x-filament-panels::page>
    <div class="space-y-6">
        @if($this->reimportHeaderId && $this->targetHeader && $this->activeUnlockRequest)
            <x-filament::section>
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                            Mode Import Ulang Terkendali (Controlled Reimport)
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Target RKA #{{ $this->targetHeader->id }} — {{ $this->targetHeader->department?->name }} ({{ $this->targetHeader->schedule?->fiscal_year }} {{ $this->targetHeader->schedule?->phase_name }}).
                            Status RKA tetap <strong>APPROVED</strong> dan akan dikunci kembali secara otomatis setelah import ulang selesai.
                        </p>
                    </div>
                    <x-filament::badge color="warning">
                        Berlaku s/d {{ $this->activeUnlockRequest->reimport_expires_at?->format('d/m/Y H:i') }}
                    </x-filament::badge>
                </div>
            </x-filament::section>
        @elseif($this->draftHeaderId && $this->targetHeader)
            <x-filament::section>
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div class="space-y-1">
                        <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                            Import Approved RKA ke RKA Draft #{{ $this->targetHeader->id }}
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Target: <strong>{{ $this->targetHeader->department?->name }}</strong>
                            ({{ $this->targetHeader->schedule?->fiscal_year }} — {{ $this->targetHeader->schedule?->period_range }}).
                            {{ $this->targetHeader->indicators->count() }} indikator Finance dipertahankan.
                            Setelah import berhasil, <strong>Pagu Budget</strong> akan diperbarui dari <strong>Usulan Awal Unit</strong> (worksheet RINGKASAN) dan status RKA akan langsung menjadi <strong>APPROVED</strong> serta dapat dilihat melalui halaman <strong>Review RKA</strong>.
                        </p>
                    </div>
                    <x-filament::badge color="warning">DRAFT → APPROVED</x-filament::badge>
                </div>
            </x-filament::section>
        @else
            <x-filament::section>
                <div class="space-y-1">
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                        Import Approved RKA dari Workbook RKA Asli
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Unggah file Excel <code>.xlsx</code> workbook RKA asli unit Anda yang telah disetujui.
                        Sistem akan membaca <code>IDENTITAS</code>, <code>RINGKASAN</code>, <code>REKAP</code>, <code>DAFTAR PENYESUAIAN</code>, <code>BERITA ACARA</code>, serta enam worksheet <code>Belanja</code> secara otomatis, mencocokkan unit dan rekening, lalu menampilkan preview sebelum disimpan dengan status <strong>APPROVED</strong> dan terkunci otomatis.
                    </p>
                </div>
            </x-filament::section>
        @endif

        <x-filament::section>
            <form wire:submit="runPreview" class="space-y-4">
                {{ $this->form }}

                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <x-filament::button type="submit" icon="heroicon-o-magnifying-glass" color="primary">
                        Preview & Validasi Data
                    </x-filament::button>

                    @if($this->previewResult)
                        <x-filament::button type="button" color="gray" wire:click="resetPreview">
                            Reset Preview
                        </x-filament::button>
                    @endif
                </div>
            </form>
        </x-filament::section>

        @if($this->previewResult)
            @php
                $summary = $this->previewResult['summary'] ?? [];
                $hasFatal = (bool) ($this->previewResult['has_fatal_errors'] ?? false);
                $fatalReason = $this->previewResult['fatal_reason'] ?? null;
                $belanjaRows = $this->previewResult['belanja_rows'] ?? [];
                $adjustmentRows = $this->previewResult['adjustment_rows'] ?? [];
                $diffCount = (int) ($summary['diff_count'] ?? 0);
                $invalidTotal = (int) ($summary['not_found_count'] ?? 0) + (int) ($summary['invalid_count'] ?? 0);
                $targetDraft = $this->previewResult['target_draft'] ?? null;
                $identitasPreview = $this->previewResult['identitas'] ?? [];
                $sheetsMetaPreview = $this->previewResult['sheets_meta'] ?? [];
            @endphp

            <x-filament::section>
                <x-slot name="heading">
                    Hasil Preview & Validasi ({{ $this->previewResult['original_filename'] ?? '-' }})
                </x-slot>

                @if($targetDraft)
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3 mb-4 text-xs">
                        <div class="rounded-lg border border-primary-200 bg-primary-50/40 p-3.5 dark:border-primary-800 dark:bg-primary-950/20 space-y-1">
                            <div class="font-semibold text-primary-700 dark:text-primary-300 mb-1.5">Identitas Target (RKA Draft #{{ $targetDraft['rka_header_id'] }})</div>
                            <div><strong>Unit:</strong> {{ $targetDraft['department_name'] }}</div>
                            <div><strong>Tahun / Periode:</strong> {{ $targetDraft['fiscal_year'] }} ({{ $targetDraft['period_range'] }})</div>
                            <div><strong>Status Saat Ini:</strong> {{ $targetDraft['status'] }} → <strong>Status Akhir:</strong> {{ $targetDraft['final_status'] ?? 'APPROVED' }}</div>
                            <div><strong>Pagu Awal Draft:</strong> Rp {{ number_format((float) $targetDraft['pagu_budget'], 0, ',', '.') }}</div>
                            <div><strong>Indikator Finance:</strong> {{ $targetDraft['indicator_count'] }} (dipertahankan)</div>
                        </div>
                        <div class="rounded-lg border border-gray-200 p-3.5 dark:border-gray-700 space-y-1">
                            <div class="font-semibold text-gray-900 dark:text-gray-100 mb-1.5">Identitas File Sumber (Excel)</div>
                            <div><strong>Nama File:</strong> {{ $this->previewResult['original_filename'] ?? '-' }}</div>
                            <div><strong>Unit Asal Excel:</strong> {{ $identitasPreview['unit_kerja'] ?? '-' }}</div>
                            <div><strong>Tahun Sumber:</strong> {{ $identitasPreview['tahun_anggaran'] ?? '-' }}</div>
                            <div><strong>Usulan Awal Unit ({{ $targetDraft['pagu_awal_source'] ?? 'RINGKASAN!C6' }}):</strong> <span class="font-semibold text-primary-700 dark:text-primary-300">Rp {{ number_format((float) ($targetDraft['new_pagu_budget'] ?? 0), 0, ',', '.') }}</span></div>
                            <div><strong>Worksheet Belanja:</strong> {{ count($sheetsMetaPreview['belanja_sheets'] ?? []) }} | <strong>Total Item:</strong> {{ count($belanjaRows) }}</div>
                        </div>
                        <div class="rounded-lg border border-gray-200 p-3.5 dark:border-gray-700 space-y-1">
                            <div class="font-semibold text-gray-900 dark:text-gray-100 mb-1.5">Ringkasan Hasil Import & Status Akhir</div>
                            <div><strong>Item Baru Ditambahkan:</strong> {{ $targetDraft['new_item_count'] }} (Duplikat dilewati: {{ $targetDraft['duplicate_item_count'] }})</div>
                            <div><strong>Adjustment Baru:</strong> {{ $targetDraft['new_adjustment_count'] }} (Sec A: {{ $targetDraft['section_a_count'] ?? 0 }}, Sec B: {{ $targetDraft['section_b_count'] ?? 0 }}, Duplikat: {{ $targetDraft['duplicate_adjustment_count'] }})</div>
                            <div><strong>Pagu Budget Baru:</strong> Rp {{ number_format((float) ($targetDraft['new_pagu_budget'] ?? 0), 0, ',', '.') }}</div>
                            <div><strong>Butuh Pemilihan Unit:</strong> {{ (int) ($summary['incomplete_unit_count'] ?? 0) }} | <strong>Perlu Perhatian:</strong> {{ $diffCount + $invalidTotal }}</div>
                        </div>
                    </div>
                    <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50/70 px-4 py-2.5 text-xs text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-300">
                        <strong>Perhatian:</strong> Setelah import berhasil, RKA akan langsung berstatus <strong>APPROVED</strong> (terkunci) dan dapat dilihat melalui halaman <strong>Review RKA</strong>. Indikator kinerja yang sudah disiapkan Finance tetap dipertahankan.
                    </div>
                @endif

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Item BELANJA</div>
                        <div class="mt-1 text-xl font-bold text-gray-950 dark:text-white">{{ $summary['total_belanja'] ?? 0 }}</div>
                        <div class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                            Disetujui: Rp {{ number_format((float) ($summary['total_approved'] ?? 0), 0, ',', '.') }}
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Penyesuaian</div>
                        <div class="mt-1 text-xl font-bold text-gray-950 dark:text-white">{{ $summary['total_adjustment'] ?? 0 }}</div>
                        <div class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                            Sesuai: {{ $summary['valid_count'] ?? 0 }} | Perhatian: {{ $diffCount }}
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Status Validasi</div>
                        <div class="mt-1">
                            @if($hasFatal)
                                <x-filament::badge color="danger">TIDAK VALID ({{ $invalidTotal }} Error)</x-filament::badge>
                            @elseif($diffCount > 0)
                                <x-filament::badge color="warning">PERLU PERHATIAN ({{ $diffCount }})</x-filament::badge>
                            @else
                                <x-filament::badge color="success">SESUAI & SIAP IMPORT</x-filament::badge>
                            @endif
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Target Status RKA</div>
                        <div class="mt-1">
                            <x-filament::badge color="success">APPROVED & TERKUNCI</x-filament::badge>
                        </div>
                    </div>
                </div>

                @if(($this->previewResult['format'] ?? 'legacy') === 'native')
                    @php
                        $paguAwalUnit = $summary['pagu_awal_unit'] ?? null;
                        $paguSource = $summary['pagu_awal_source'] ?? null;
                        $paguLabel = $summary['pagu_awal_label'] ?? 'Usulan Awal Unit';
                        $paguStatus = $summary['pagu_validation_status'] ?? 'TIDAK DITEMUKAN';
                    @endphp
                    <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <div class="rounded-lg border border-primary-200 bg-primary-50/50 p-4 dark:border-primary-800 dark:bg-primary-950/30">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-semibold uppercase tracking-wide text-primary-700 dark:text-primary-300">Pagu Awal Unit (Sumber Excel)</span>
                                <x-filament::badge :color="$paguStatus === 'VALID' ? 'success' : 'danger'">
                                    {{ $paguStatus }}
                                </x-filament::badge>
                            </div>
                            <div class="mt-2 text-2xl font-bold text-gray-950 dark:text-white">
                                {{ $paguAwalUnit !== null ? 'Rp ' . number_format((float) $paguAwalUnit, 0, ',', '.') : 'Tidak Ditemukan' }}
                            </div>
                            <div class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                                Sumber: Worksheet RINGKASAN — {{ $paguLabel }}@if($paguSource) ({{ $paguSource }})@endif
                            </div>
                        </div>

                        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Agregasi Worksheet BELANJA</div>
                            <div class="mt-2 space-y-1 text-sm">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-gray-600 dark:text-gray-400">Total Item BELANJA Usulan:</span>
                                    <span class="font-semibold text-gray-950 dark:text-white">Rp {{ number_format((float) ($summary['total_proposed'] ?? 0), 0, ',', '.') }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-gray-600 dark:text-gray-400">Total Item BELANJA Disetujui:</span>
                                    <span class="font-semibold text-emerald-600 dark:text-emerald-400">Rp {{ number_format((float) ($summary['total_approved'] ?? 0), 0, ',', '.') }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Ringkasan Penyesuaian</div>
                            <div class="mt-2 space-y-1 text-sm">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-gray-600 dark:text-gray-400">Total Penyesuaian Section A:</span>
                                    <span class="font-semibold text-gray-950 dark:text-white">Rp {{ number_format((float) ($summary['total_section_a_proposed'] ?? 0), 0, ',', '.') }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-gray-600 dark:text-gray-400">Total Penyesuaian Section B:</span>
                                    <span class="font-semibold text-gray-950 dark:text-white">Rp {{ number_format((float) ($summary['total_section_b_proposed'] ?? 0), 0, ',', '.') }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-gray-600 dark:text-gray-400">Total Penyesuaian Disetujui:</span>
                                    <span class="font-semibold text-primary-600 dark:text-primary-400">Rp {{ number_format((float) ($summary['total_adjustment_approved'] ?? 0), 0, ',', '.') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @php
                    $errorsList = $this->previewResult['errors'] ?? [];
                    $warningsList = $this->previewResult['warnings'] ?? [];
                    $infosList = $this->previewResult['infos'] ?? [];
                    $identitas = $this->previewResult['identitas'] ?? null;
                    $reconciliation = $this->previewResult['reconciliation'] ?? [];
                    $beritaAcara = $this->previewResult['berita_acara'] ?? null;
                    $rekap = $this->previewResult['rekap'] ?? null;
                @endphp

                @if($hasFatal && ($fatalReason || count($errorsList) > 0))
                    <div class="mb-4 rounded-lg border border-danger-300 bg-danger-50 p-4 text-sm text-danger-800 dark:border-danger-700 dark:bg-danger-950 dark:text-danger-200">
                        <div class="font-semibold mb-1">Kesalahan Fatal (Import Diblokir):</div>
                        @if(count($errorsList) > 0)
                            <ul class="list-disc pl-5 space-y-1">
                                @foreach($errorsList as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        @else
                            <p>{{ $fatalReason }}</p>
                        @endif
                    </div>
                @endif

                @if(count($warningsList) > 0)
                    <div class="mb-4 rounded-lg border border-warning-300 bg-warning-50 p-4 text-sm text-warning-800 dark:border-warning-700 dark:bg-warning-950 dark:text-warning-200">
                        <div class="font-semibold mb-1">Peringatan (Perlu Perhatian):</div>
                        <ul class="list-disc pl-5 space-y-1">
                            @foreach($warningsList as $warn)
                                <li>{{ $warn }}</li>
                            @endforeach
                        </ul>
                    </div>
                @elseif(! $hasFatal && $diffCount > 0)
                    <div class="mb-4 rounded-lg border border-warning-300 bg-warning-50 p-4 text-sm text-warning-800 dark:border-warning-700 dark:bg-warning-950 dark:text-warning-200">
                        <div class="font-semibold mb-1">Peringatan (Perlu Perhatian):</div>
                        <p>Terdapat {{ $diffCount }} baris dengan status BERBEDA atau PERLU PERHATIAN pada tabel di bawah. Periksa sebelum konfirmasi.</p>
                    </div>
                @endif

                @if(count($infosList) > 0)
                    <div class="mb-4 rounded-lg border border-info-300 bg-info-50 p-3 text-xs text-info-800 dark:border-info-700 dark:bg-info-950 dark:text-info-200">
                        @foreach($infosList as $info)
                            <div>{{ $info }}</div>
                        @endforeach
                    </div>
                @endif

                <div class="flex flex-wrap gap-2 border-b border-gray-200 dark:border-gray-700 mb-4">
                    <button
                        type="button"
                        wire:click="$set('activePreviewTab', 'belanja')"
                        class="px-4 py-2 text-sm font-medium border-b-2 {{ $this->activePreviewTab === 'belanja' ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
                    >
                        Belanja ({{ count($belanjaRows) }})
                    </button>
                    <button
                        type="button"
                        wire:click="$set('activePreviewTab', 'adjustments')"
                        class="px-4 py-2 text-sm font-medium border-b-2 {{ $this->activePreviewTab === 'adjustments' ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
                    >
                        Daftar Penyesuaian ({{ count($adjustmentRows) }})
                    </button>
                    @if($identitas)
                        <button
                            type="button"
                            wire:click="$set('activePreviewTab', 'rekap')"
                            class="px-4 py-2 text-sm font-medium border-b-2 {{ $this->activePreviewTab === 'rekap' ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
                        >
                            Rekap & Rekonsiliasi ({{ count($reconciliation) }})
                        </button>
                        <button
                            type="button"
                            wire:click="$set('activePreviewTab', 'identitas')"
                            class="px-4 py-2 text-sm font-medium border-b-2 {{ $this->activePreviewTab === 'identitas' ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
                        >
                            Identitas & Berita Acara
                        </button>
                    @endif
                </div>

                @if($this->activePreviewTab === 'belanja')
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="px-3 py-2">Baris</th>
                                    <th class="px-3 py-2">Ref ID</th>
                                    <th class="px-3 py-2">Program / Kegiatan</th>
                                    <th class="px-3 py-2">Kode Rekening</th>
                                    <th class="px-3 py-2">Uraian Item</th>
                                    <th class="px-3 py-2 text-right">Nilai Usulan</th>
                                    <th class="px-3 py-2 text-right">Nilai Disetujui</th>
                                    <th class="px-3 py-2">Status Item</th>
                                    <th class="px-3 py-2">Validasi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($belanjaRows as $row)
                                    @php
                                        $vStatus = $row['validation_status'] ?? 'SESUAI';
                                        $badgeColor = match($vStatus) {
                                            'SESUAI' => 'success',
                                            'BERBEDA', 'PERLU PERHATIAN' => 'warning',
                                            default => 'danger',
                                        };
                                    @endphp
                                    <tr>
                                        <td class="px-3 py-2">{{ $row['row_number'] ?? '-' }}</td>
                                        <td class="px-3 py-2">{{ $row['item_ref_id'] ?? '-' }}</td>
                                        <td class="px-3 py-2">{{ $row['program_kegiatan'] ?? '-' }}</td>
                                        <td class="px-3 py-2 font-mono">{{ $row['kode_rekening'] ?? '-' }}</td>
                                        <td class="px-3 py-2">{{ $row['uraian_item'] ?? '-' }}</td>
                                        <td class="px-3 py-2 text-right">{{ number_format((float) ($row['nilai_usulan'] ?? 0), 0, ',', '.') }}</td>
                                        <td class="px-3 py-2 text-right">{{ number_format((float) ($row['nilai_disetujui'] ?? 0), 0, ',', '.') }}</td>
                                        <td class="px-3 py-2">{{ $row['status_item'] ?? '-' }}</td>
                                        <td class="px-3 py-2">
                                            <x-filament::badge :color="$badgeColor">{{ $vStatus }}</x-filament::badge>
                                            @if(!empty($row['validation_message']))
                                                <div class="text-[11px] text-gray-500 mt-0.5">{{ $row['validation_message'] }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-3 py-4 text-center text-gray-500">Tidak ada baris item pada worksheet BELANJA.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @elseif($this->activePreviewTab === 'adjustments')
                    @php
                        $incompleteUnitCount = (int) ($summary['incomplete_unit_count'] ?? 0);
                        $completeUnitCount = (int) ($summary['complete_unit_count'] ?? 0);
                        $ambiguousUnitCount = (int) ($summary['ambiguous_unit_count'] ?? 0);
                        $notRequiredUnitCount = (int) ($summary['not_required_unit_count'] ?? 0);
                        $missingUnitItems = $summary['missing_unit_items'] ?? [];
                    @endphp
                    <div class="space-y-3 mb-4">
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <x-filament::badge color="success">Valid: {{ $completeUnitCount }}</x-filament::badge>
                            <x-filament::badge :color="$incompleteUnitCount > 0 ? 'warning' : 'gray'">Belum Dipetakan: {{ $incompleteUnitCount }}</x-filament::badge>
                            @if($ambiguousUnitCount > 0)
                                <x-filament::badge color="danger">Ambigu: {{ $ambiguousUnitCount }}</x-filament::badge>
                            @endif
                            <x-filament::badge color="gray">Tidak Memerlukan Unit: {{ $notRequiredUnitCount }}</x-filament::badge>
                        </div>

                        @if($incompleteUnitCount > 0)
                            <div class="rounded-lg border border-warning-300 bg-warning-50 p-3 text-xs text-warning-900 dark:border-warning-700 dark:bg-warning-950/30 dark:text-warning-200">
                                <div class="font-semibold mb-1">
                                    Masih ada {{ $incompleteUnitCount }} item penyesuaian yang membutuhkan tindakan pengguna (belum memiliki unit tujuan/asal yang valid).
                                </div>
                                <p class="mb-2 text-[11px]">
                                    Tentukan unit terkait pada masing-masing baris di bawah sebelum melakukan finalisasi import.
                                </p>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach(array_slice($missingUnitItems, 0, 5) as $missingItem)
                                        <button
                                            type="button"
                                            wire:click="mountAction('assignUnit', { adj_ref_id: '{{ $missingItem['adj_ref_id'] ?? '' }}' })"
                                            class="inline-flex items-center rounded bg-warning-200 px-2 py-0.5 text-[11px] font-medium text-warning-900 hover:bg-warning-300 dark:bg-warning-800 dark:text-warning-100"
                                        >
                                            {{ $missingItem['section_label'] ?? 'Item' }}: {{ \Illuminate\Support\Str::limit($missingItem['description'] ?? '-', 32) }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="px-3 py-2">Baris</th>
                                    <th class="px-3 py-2">Bagian</th>
                                    <th class="px-3 py-2">Status</th>
                                    <th class="px-3 py-2">Kode Rekening</th>
                                    <th class="px-3 py-2">Uraian</th>
                                    <th class="px-3 py-2">Volume</th>
                                    <th class="px-3 py-2">Unit Asal/Sumber</th>
                                    <th class="px-3 py-2">Unit Tujuan</th>
                                    <th class="px-3 py-2 text-right">Jumlah Usulan</th>
                                    <th class="px-3 py-2 text-right">Jumlah Disetujui</th>
                                    <th class="px-3 py-2">Status Pemetaan</th>
                                    <th class="px-3 py-2">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($adjustmentRows as $row)
                                    @php
                                        $sectionType = strtoupper((string) ($row['section'] ?? 'A'));
                                        $actionType = strtoupper((string) ($row['action_type'] ?? ''));
                                        $vStatus = $row['relation_status'] ?? $row['validation_status'] ?? 'SESUAI';
                                        $badgeColor = match($vStatus) {
                                            'UNIT TELAH DITENTUKAN', 'SESUAI', 'RELASI SESUAI' => 'success',
                                            'TIDAK MEMERLUKAN UNIT', 'RELASI TIDAK DIPERLUKAN' => 'gray',
                                            'BELUM DITENTUKAN', 'PERLU PERHATIAN', 'BERBEDA' => 'warning',
                                            default => 'danger',
                                        };
                                        $assignedUnitName = $sectionType === 'A'
                                            ? ($row['destination_unit_name'] ?? null)
                                            : ($row['target_unit_name'] ?? null);
                                        $assignedUnitCode = $sectionType === 'A'
                                            ? ($row['destination_unit_code'] ?? null)
                                            : ($row['target_unit_code'] ?? null);
                                        $unitSource = $row['unit_source'] ?? null;
                                        $requiresManualUnit = ($sectionType === 'A' && $actionType === 'MOVED') || $sectionType === 'B';
                                    @endphp
                                    <tr>
                                        <td class="px-3 py-2">{{ $row['row_number'] ?? '-' }}</td>
                                        <td class="px-3 py-2">{{ $row['section'] ?? '-' }}</td>
                                        <td class="px-3 py-2">{{ $row['status_penyesuaian'] ?? '-' }}</td>
                                        <td class="px-3 py-2 font-mono">{{ $row['kode_rekening'] ?? '-' }}</td>
                                        <td class="px-3 py-2">{{ $row['uraian'] ?? '-' }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap">{{ $row['volume'] ?? '-' }} {{ $row['satuan'] ?? '' }}</td>
                                        <td class="px-3 py-2">
                                            @if($sectionType === 'B')
                                                @if(!empty($row['target_unit_name']))
                                                    <div class="font-medium text-gray-900 dark:text-gray-100">
                                                        {{ $row['target_unit_name'] }}{{ !empty($row['target_unit_code']) ? " ({$row['target_unit_code']})" : '' }}
                                                    </div>
                                                @else
                                                    <span class="text-warning-600 dark:text-warning-400 font-medium">Belum ditentukan</span>
                                                @endif
                                                @if(!empty($row['unit_asal_excel']))
                                                    <div class="text-[11px] text-gray-500">Excel: {{ $row['unit_asal_excel'] }}</div>
                                                @endif
                                                @if(!empty($unitSource))
                                                    <span class="inline-flex rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300 mt-0.5">{{ $unitSource }}</span>
                                                @endif
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2">
                                            @if($sectionType === 'A' && $actionType === 'MOVED')
                                                @if(!empty($row['destination_unit_name']))
                                                    <div class="font-medium text-gray-900 dark:text-gray-100">
                                                        {{ $row['destination_unit_name'] }}{{ !empty($row['destination_unit_code']) ? " ({$row['destination_unit_code']})" : '' }}
                                                    </div>
                                                @else
                                                    <span class="text-warning-600 dark:text-warning-400 font-medium">Belum ditentukan</span>
                                                @endif
                                                @if(!empty($row['unit_tujuan_excel']))
                                                    <div class="text-[11px] text-gray-500">Excel: {{ $row['unit_tujuan_excel'] }}</div>
                                                @endif
                                                @if(!empty($unitSource))
                                                    <span class="inline-flex rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300 mt-0.5">{{ $unitSource }}</span>
                                                @endif
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-right">{{ number_format((float) ($row['jumlah_usulan'] ?? 0), 0, ',', '.') }}</td>
                                        <td class="px-3 py-2 text-right">{{ number_format((float) ($row['jumlah_disetujui'] ?? 0), 0, ',', '.') }}</td>
                                        <td class="px-3 py-2">
                                            <x-filament::badge :color="$badgeColor">{{ $vStatus }}</x-filament::badge>
                                            @if(!empty($row['validation_message']))
                                                <div class="text-[11px] text-gray-500 mt-0.5">{{ $row['validation_message'] }}</div>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap">
                                            @if($requiresManualUnit)
                                                @php
                                                    $buttonLabel = $assignedUnitName
                                                        ? 'Edit Unit'
                                                        : ($sectionType === 'A' ? 'Tentukan Unit Tujuan' : 'Tentukan Unit Asal/Sumber');
                                                @endphp
                                                <x-filament::button
                                                    type="button"
                                                    size="xs"
                                                    :color="$assignedUnitName ? 'gray' : 'primary'"
                                                    wire:click="mountAction('assignUnit', { adj_ref_id: '{{ $row['adj_ref_id'] ?? '' }}' })"
                                                >
                                                    {{ $buttonLabel }}
                                                </x-filament::button>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="12" class="px-3 py-4 text-center text-gray-500">Tidak ada baris penyesuaian pada worksheet DAFTAR PENYESUAIAN.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @elseif($this->activePreviewTab === 'rekap')
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="px-3 py-2">Cakupan Rekonsiliasi</th>
                                    <th class="px-3 py-2 text-right">Nilai Referensi (Excel)</th>
                                    <th class="px-3 py-2 text-right">Hasil Parsing</th>
                                    <th class="px-3 py-2 text-right">Selisih</th>
                                    <th class="px-3 py-2">Status</th>
                                    <th class="px-3 py-2">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($reconciliation as $check)
                                    @php
                                        $cStatus = $check['status'] ?? 'SESUAI';
                                        $cColor = match($cStatus) {
                                            'SESUAI' => 'success',
                                            'PERLU PERHATIAN' => 'warning',
                                            default => 'danger',
                                        };
                                    @endphp
                                    <tr>
                                        <td class="px-3 py-2 font-medium">{{ $check['scope'] }}</td>
                                        <td class="px-3 py-2 text-right">{{ $check['expected'] !== null ? number_format((float) $check['expected'], 0, ',', '.') : '-' }}</td>
                                        <td class="px-3 py-2 text-right">{{ number_format((float) ($check['actual'] ?? 0), 0, ',', '.') }}</td>
                                        <td class="px-3 py-2 text-right">{{ $check['diff'] !== null ? number_format((float) $check['diff'], 0, ',', '.') : '-' }}</td>
                                        <td class="px-3 py-2"><x-filament::badge :color="$cColor">{{ $cStatus }}</x-filament::badge></td>
                                        <td class="px-3 py-2">{{ $check['notes'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 text-xs">
                        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700 space-y-1.5">
                            <div class="font-semibold text-sm mb-2">Identitas Unit & Pengesahan Workbook</div>
                            <div><strong>Unit Kerja:</strong> {{ $identitas['unit_kerja'] ?? '-' }}</div>
                            <div><strong>Tahun Anggaran:</strong> {{ $identitas['tahun_anggaran'] ?? '-' }} ({{ $identitas['periode'] ?? '-' }})</div>
                            <div><strong>Pagu Anggaran Unit:</strong> {{ $identitas['pagu_anggaran'] !== null ? 'Rp '.number_format((float) $identitas['pagu_anggaran'], 0, ',', '.') : 'PAGU BELUM DIISI' }}</div>
                            <div><strong>Direktur:</strong> {{ $identitas['nama_direktur'] ?? '-' }} ({{ $identitas['nrp_direktur'] ?? '-' }})</div>
                            <div><strong>Reviewer:</strong> {{ $identitas['nama_reviewer'] ?? '-' }} — {{ $identitas['jabatan_reviewer'] ?? '-' }}</div>
                            <div><strong>Tanggal Review:</strong> {{ $identitas['tanggal_review'] ?? '-' }}</div>
                        </div>
                        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700 space-y-1.5">
                            <div class="font-semibold text-sm mb-2">Informasi Berita Acara</div>
                            <div><strong>Nomor:</strong> {{ $beritaAcara['nomor'] ?? '-' }}</div>
                            <div><strong>Status Placeholder:</strong> {{ !empty($beritaAcara['has_placeholder']) ? 'Masih mengandung placeholder ([....])' : 'Lengkap' }}</div>
                            <div><strong>Ringkasan Pembuka:</strong> {{ $beritaAcara['pembuka'] ?? '-' }}</div>
                        </div>
                    </div>
                @endif

                @php
                    $incompleteUnitsOnConfirm = (int) ($summary['incomplete_unit_count'] ?? 0);
                @endphp
                <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-t border-gray-200 pt-4 dark:border-gray-700">
                    <div>
                        @if(! $hasFatal && $incompleteUnitsOnConfirm > 0)
                            <p class="text-xs font-medium text-warning-600 dark:text-warning-400">
                                Lengkapi {{ $incompleteUnitsOnConfirm }} unit tujuan/asal pada tab Daftar Penyesuaian sebelum menyimpan Approved RKA.
                            </p>
                        @endif
                    </div>
                    <div class="flex items-center justify-end gap-3">
                        @if(! $hasFatal)
                            @php
                                $confirmLabel = match (true) {
                                    $this->draftHeaderId !== null => 'Konfirmasi & Impor Approved RKA',
                                    $this->reimportHeaderId !== null => 'Konfirmasi & Jalankan Import Ulang',
                                    default => 'Konfirmasi & Simpan Approved RKA',
                                };
                            @endphp
                            <x-filament::button
                                type="button"
                                color="success"
                                icon="heroicon-o-check-circle"
                                :disabled="$incompleteUnitsOnConfirm > 0"
                                x-on:click="$dispatch('open-modal', { id: 'confirm-approved-import-modal' })"
                            >
                                {{ $confirmLabel }}
                            </x-filament::button>

                            <x-filament::modal
                                id="confirm-approved-import-modal"
                                width="2xl"
                                icon="heroicon-o-document-arrow-up"
                                icon-color="success"
                                :close-by-clicking-away="false"
                                :close-by-escaping="false"
                            >
                                <x-slot name="heading">
                                    Konfirmasi Import Approved RKA
                                </x-slot>

                                <x-slot name="description">
                                    Pastikan Anda telah memeriksa seluruh hasil preview sebelum melanjutkan proses import.
                                </x-slot>

                                @php
                                    $modalTargetUnit = $this->targetHeader?->department?->name
                                        ?? ($targetDraft['department_name'] ?? ($identitas['unit_kerja'] ?? '-'));
                                    $modalFiscalYear = $this->targetHeader?->schedule?->fiscal_year
                                        ?? ($targetDraft['fiscal_year'] ?? ($identitas['tahun_anggaran'] ?? '-'));
                                    $modalHeaderId = $this->draftHeaderId ?? $this->reimportHeaderId;
                                    $modalStatus = $this->targetHeader?->status
                                        ?? ($targetDraft['status'] ?? 'DRAFT');
                                    $modalPaguAwal = $targetDraft['new_pagu_budget']
                                        ?? ($summary['pagu_awal_unit'] ?? ($identitas['pagu_anggaran'] ?? 0));
                                    $modalValidationLabel = ($incompleteUnitsOnConfirm > 0 || $hasFatal)
                                        ? 'Perlu Perhatian'
                                        : 'Siap Diimpor (Valid)';
                                @endphp

                                <div class="space-y-4 text-sm text-gray-700 dark:text-gray-200">
                                    {{-- Informasi RKA Tujuan --}}
                                    <div class="rounded-xl border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-700 dark:bg-gray-800/60">
                                        <h4 class="mb-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                            Informasi RKA Tujuan
                                        </h4>
                                        <dl class="grid grid-cols-1 gap-x-4 gap-y-2 text-xs sm:grid-cols-2">
                                            <div>
                                                <dt class="text-gray-500 dark:text-gray-400">Unit / Departemen</dt>
                                                <dd class="font-semibold text-gray-950 dark:text-white">{{ $modalTargetUnit }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-gray-500 dark:text-gray-400">Tahun Anggaran</dt>
                                                <dd class="font-semibold text-gray-950 dark:text-white">{{ $modalFiscalYear }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-gray-500 dark:text-gray-400">ID RKA Draft</dt>
                                                <dd class="font-semibold text-gray-950 dark:text-white">{{ $modalHeaderId ? '#' . $modalHeaderId : 'Baru' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-gray-500 dark:text-gray-400">Status Saat Ini</dt>
                                                <dd>
                                                    <x-filament::badge color="info">{{ $modalStatus }}</x-filament::badge>
                                                </dd>
                                            </div>
                                        </dl>
                                    </div>

                                    {{-- Ringkasan Validasi --}}
                                    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900/60">
                                        <h4 class="mb-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                            Ringkasan Validasi Preview
                                        </h4>
                                        <dl class="grid grid-cols-2 gap-x-4 gap-y-2.5 text-xs sm:grid-cols-3">
                                            <div>
                                                <dt class="text-gray-500 dark:text-gray-400">Total Item Belanja</dt>
                                                <dd class="font-semibold text-gray-950 dark:text-white">{{ number_format((int) ($summary['total_belanja'] ?? count($belanjaRows)), 0, ',', '.') }} item</dd>
                                            </div>
                                            <div>
                                                <dt class="text-gray-500 dark:text-gray-400">Total Penyesuaian</dt>
                                                <dd class="font-semibold text-gray-950 dark:text-white">{{ number_format((int) ($summary['total_adjustment'] ?? count($adjustmentRows)), 0, ',', '.') }} baris</dd>
                                            </div>
                                            <div>
                                                <dt class="text-gray-500 dark:text-gray-400">Status Validasi</dt>
                                                <dd class="font-semibold text-emerald-700 dark:text-emerald-400">{{ $modalValidationLabel }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-gray-500 dark:text-gray-400">Usulan Awal Unit</dt>
                                                <dd class="font-mono font-semibold text-gray-950 dark:text-white">Rp {{ number_format((float) $modalPaguAwal, 0, ',', '.') }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-gray-500 dark:text-gray-400">Total Nilai Usulan</dt>
                                                <dd class="font-mono font-semibold text-gray-950 dark:text-white">Rp {{ number_format((float) ($summary['total_proposed'] ?? 0), 0, ',', '.') }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-gray-500 dark:text-gray-400">Total Nilai Disetujui</dt>
                                                <dd class="font-mono font-semibold text-emerald-700 dark:text-emerald-400">Rp {{ number_format((float) ($summary['total_approved'] ?? 0), 0, ',', '.') }}</dd>
                                            </div>
                                        </dl>
                                    </div>

                                    {{-- Dampak Import --}}
                                    <div class="rounded-xl border border-sky-200 bg-sky-50/70 p-4 text-xs text-sky-900 dark:border-sky-800/70 dark:bg-sky-950/30 dark:text-sky-200">
                                        <h4 class="mb-2 font-semibold text-sky-950 dark:text-sky-100">Dampak Import</h4>
                                        <ol class="list-decimal space-y-1 pl-4">
                                            <li>Data item belanja dan penyesuaian akan dimasukkan berdasarkan hasil preview.</li>
                                            <li>Nilai <code>pagu_budget</code> akan diperbarui berdasarkan nilai Usulan Awal Unit dari workbook.</li>
                                            <li>Status RKA akan langsung berubah menjadi <strong>APPROVED</strong> setelah proses import berhasil.</li>
                                            <li>Data hasil import akan dapat dilihat melalui halaman <strong>Review RKA</strong>.</li>
                                        </ol>
                                    </div>

                                    {{-- Peringatan --}}
                                    <div class="rounded-xl border border-amber-200 bg-amber-50/80 px-4 py-3 text-xs text-amber-900 dark:border-amber-800/70 dark:bg-amber-950/30 dark:text-amber-200">
                                        Proses ini akan mengubah data RKA Draft yang dipilih. Pastikan seluruh data dan hasil validasi sudah benar sebelum melanjutkan.
                                    </div>
                                </div>

                                <x-slot name="footerActions">
                                    <x-filament::button
                                        type="button"
                                        color="gray"
                                        icon="heroicon-o-x-mark"
                                        x-on:click="$dispatch('close-modal', { id: 'confirm-approved-import-modal' })"
                                        wire:loading.attr="disabled"
                                        wire:target="confirmImport"
                                    >
                                        Batal
                                    </x-filament::button>

                                    <x-filament::button
                                        type="button"
                                        color="success"
                                        icon="heroicon-o-check-circle"
                                        wire:click="confirmImport"
                                        wire:loading.attr="disabled"
                                        wire:target="confirmImport"
                                    >
                                        <span wire:loading.remove wire:target="confirmImport">Ya, Import Approved RKA</span>
                                        <span wire:loading wire:target="confirmImport">Memproses Import...</span>
                                    </x-filament::button>
                                </x-slot>
                            </x-filament::modal>
                        @endif
                    </div>
                </div>
            </x-filament::section>
        @endif

        <x-filament::section>
            <x-slot name="heading">Riwayat Batch Import Approved RKA</x-slot>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-3 py-2">Batch</th>
                            <th class="px-3 py-2">Tipe</th>
                            <th class="px-3 py-2">Departemen</th>
                            <th class="px-3 py-2">File</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Pengguna</th>
                            <th class="px-3 py-2">Waktu</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($this->getRecentBatches() as $batch)
                            <tr>
                                <td class="px-3 py-2">#{{ $batch->id }} (RKA #{{ $batch->rka_header_id ?? '-' }})</td>
                                <td class="px-3 py-2">{{ $batch->import_type }}</td>
                                <td class="px-3 py-2">{{ $batch->department?->name ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $batch->original_filename }}</td>
                                <td class="px-3 py-2">
                                    <x-filament::badge :color="$batch->status === 'SUCCESS' ? 'success' : 'danger'">
                                        {{ $batch->status }}
                                    </x-filament::badge>
                                </td>
                                <td class="px-3 py-2">{{ $batch->importer?->name ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $batch->created_at?->format('d/m/Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-4 text-center text-gray-500">Belum ada riwayat batch import.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
