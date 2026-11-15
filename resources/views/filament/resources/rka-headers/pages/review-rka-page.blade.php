<x-filament-panels::page>
    <div class="space-y-6">
        <div class="space-y-4">
            {{-- HEADER METADATA & STATUS --}}
            @php
                $status = strtoupper($record->rka_status ?? ($record->status ?? 'DRAFT'));

                $statusConfig = match ($status) {
                    'DRAFT' => [
                        'label' => 'Draft',
                        'classes' =>
                            'bg-gray-100 text-gray-700 ring-gray-500/20 dark:bg-gray-500/10 dark:text-gray-300 dark:ring-gray-500/20',
                        'dot' => 'bg-gray-400',
                    ],

                    'SUBMITTED' => [
                        'label' => 'Diajukan',
                        'classes' =>
                            'bg-cyan-50 text-cyan-700 ring-cyan-600/20 dark:bg-cyan-500/10 dark:text-cyan-400 dark:ring-cyan-500/20',
                        'dot' => 'bg-cyan-500',
                    ],

                    'IN_REVIEW' => [
                        'label' => 'Dalam Review',
                        'classes' =>
                            'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/20',
                        'dot' => 'bg-amber-500',
                    ],

                    'IN_APPROVED' => [
                        'label' => 'Menunggu Persetujuan',
                        'classes' =>
                            'bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-500/10 dark:text-blue-400 dark:ring-blue-500/20',
                        'dot' => 'bg-blue-500',
                    ],

                    'REVISION_REQUIRED' => [
                        'label' => 'Perlu Revisi',
                        'classes' =>
                            'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-500/20',
                        'dot' => 'bg-rose-500',
                    ],

                    'APPROVED' => [
                        'label' => 'Disetujui',
                        'classes' =>
                            'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/20',
                        'dot' => 'bg-emerald-500',
                    ],

                    default => [
                        'label' => str($status)->replace('_', ' ')->title()->toString(),
                        'classes' =>
                            'bg-gray-100 text-gray-700 ring-gray-500/20 dark:bg-gray-500/10 dark:text-gray-300 dark:ring-gray-500/20',
                        'dot' => 'bg-gray-400',
                    ],
                };

                $academicYear = $record->schedule?->academic_year ?? '2026/2027';
                $departmentName = $record->department?->name ?? 'Belum ditentukan';
            @endphp

            <section
                class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm
           dark:border-gray-700 dark:bg-gray-900">
                <div class="p-5 sm:p-6">
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">

                        {{-- Metadata --}}
                        <div class="min-w-0 space-y-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="inline-flex items-center rounded-md bg-blue-50 px-2.5 py-1
                               text-xs font-semibold tracking-wide text-blue-700
                               dark:bg-blue-500/10 dark:text-blue-400">
                                    RINGKASAN REVIEW RKA
                                </span>

                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                    Tahun Anggaran {{ $academicYear }}
                                </span>
                            </div>

                            <div>
                                <h1
                                    class="text-lg font-bold tracking-tight text-gray-900
                               sm:text-xl dark:text-white">
                                    Politeknik Kampar
                                </h1>

                                <div
                                    class="mt-1 flex items-start gap-2 text-sm
                               text-gray-600 dark:text-gray-400">
                                    <x-heroicon-o-building-office-2 class="mt-0.5 h-4 w-4 shrink-0" />

                                    <span class="font-medium">
                                        {{ $departmentName }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- Status --}}
                        <div class="flex shrink-0 flex-col items-start gap-2
                       sm:items-end">
                            <span
                                class="text-xs font-medium uppercase tracking-wider
                           text-gray-500 dark:text-gray-400">
                                Status RKA
                            </span>

                            <span @class([
                                'inline-flex items-center gap-2 rounded-lg px-3 py-2',
                                'text-xs font-semibold ring-1 ring-inset',
                                $statusConfig['classes'],
                            ])>
                                <span @class(['h-2 w-2 shrink-0 rounded-full', $statusConfig['dot']])></span>

                                {{ $statusConfig['label'] }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Accent divider --}}
                <div class="h-1 bg-gray-100 dark:bg-gray-800">
                    <div class="h-full w-20 bg-blue-600 dark:bg-blue-500"></div>
                </div>
            </section>

            {{-- 2. ACTION TOOLBAR SECTION --}}
            @if (!auth()->user()->hasRole('pemohon'))
                @php
                    $user = auth()->user();
                    $currentStatus = strtoupper($record->rka_status ?? ($record->status ?? ''));

                    $isWadirCanAction =
                        $user->hasRole('wadir_keuangan') &&
                        in_array($currentStatus, ['SUBMITTED', 'IN_REVIEW', 'REVISION_REQUIRED']);

                    $isDirekturCanAction = $user->hasRole('direktur') && $currentStatus === 'IN_APPROVED';
                @endphp

                @if ($isWadirCanAction || $isDirekturCanAction)
                    <section
                        class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm
                   dark:border-gray-700 dark:bg-gray-900">
                        <div
                            class="flex flex-col gap-4 p-4 sm:p-5
                       lg:flex-row lg:items-center lg:justify-between">
                            {{-- Informasi aksi --}}
                            <div class="flex min-w-0 items-start gap-3">
                                <div
                                    class="flex h-10 w-10 shrink-0 items-center justify-center
                               rounded-lg bg-blue-50 text-blue-600
                               dark:bg-blue-500/10 dark:text-blue-400">
                                    <x-heroicon-o-clipboard-document-check class="h-5 w-5" />
                                </div>

                                <div class="min-w-0 space-y-1">
                                    <h3
                                        class="text-sm font-semibold text-gray-900
                                   dark:text-white">
                                        Aksi Keputusan Review RKA
                                    </h3>

                                    <p
                                        class="max-w-2xl text-sm leading-relaxed
                                   text-gray-600 dark:text-gray-400">
                                        @if ($isWadirCanAction)
                                            Tinjau hasil RKA dan teruskan kepada Direktur
                                            untuk proses persetujuan berikutnya.
                                        @elseif ($isDirekturCanAction)
                                            Tetapkan keputusan akhir atas pengajuan RKA
                                            atau kembalikan untuk dilakukan revisi.
                                        @endif
                                    </p>

                                    {{-- Konteks status --}}
                                    <div class="flex flex-wrap items-center gap-2 pt-1">
                                        <span
                                            class="inline-flex items-center rounded-md
                                       bg-gray-100 px-2 py-1 text-xs font-medium
                                       text-gray-600
                                       dark:bg-gray-800 dark:text-gray-300">
                                            {{ str($currentStatus)->replace('_', ' ')->title() }}
                                        </span>

                                        <span class="text-xs text-gray-500 dark:text-gray-500">
                                            {{ $isWadirCanAction ? 'Wewenang: Wadir Keuangan' : 'Wewenang: Direktur' }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {{-- Tombol aksi Filament --}}
                            <div
                                class="flex w-full flex-col gap-2
                           sm:flex-row sm:flex-wrap sm:justify-end
                           lg:w-auto lg:shrink-0">
                                @if ($isWadirCanAction)
                                    <div class="w-full sm:w-auto">
                                        {{ $this->forwardToDirekturAction }}
                                    </div>
                                @endif

                                @if ($isDirekturCanAction)
                                    <div class="w-full sm:w-auto">
                                        {{ $this->requireRevisionDirekturAction }}
                                    </div>

                                    <div class="w-full sm:w-auto">
                                        {{ $this->approveDirekturAction }}
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Garis aksen --}}
                        <div class="h-px bg-gray-100 dark:bg-gray-800"></div>
                    </section>
                @endif
            @endif
        </div>

        {{-- NAVIGASI SHEET REVIEW RKA --}}
        <div class="space-y-5">

            @php
                $user = auth()->user();
                $isPemohon = $user?->hasRole('pemohon');
                $isDirektur = $user?->hasRole('direktur');
                $isWadir = $user?->hasRole('wadir_keuangan');

                // Filter department berdasarkan role dan status RKA
                $depts = $this->departments->filter(function ($d) use ($isDirektur, $isWadir) {
                    $status = strtoupper($d->rka_status ?? 'DRAFT');

                    if ($isDirektur) {
                        return in_array($status, ['IN_APPROVED', 'APPROVED']);
                    }

                    if ($isWadir) {
                        return in_array($status, ['SUBMITTED', 'IN_REVIEW', 'REVISION_REQUIRED']);
                    }

                    return true;
                });

                // Statistik status berdasarkan department yang telah difilter
                $statusCounts = $depts->countBy(fn($d) => strtoupper($d->rka_status ?? 'DRAFT'));

                $submittedCount = $statusCounts->get('SUBMITTED', 0);
                $inReviewCount = $statusCounts->get('IN_REVIEW', 0);
                $inApprovedCount = $statusCounts->get('IN_APPROVED', 0);
                $revisionCount = $statusCounts->get('REVISION_REQUIRED', 0);
                $approvedCount = $statusCounts->get('APPROVED', 0);
            @endphp

            {{-- 1. PANEL PEMILIHAN DEPARTMENT DAN STATISTIK --}}
            @if (!$isPemohon)
                <section
                    class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm
                   dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex flex-col gap-5 p-4 sm:p-5 xl:flex-row xl:items-end xl:justify-between">

                        {{-- Department selector --}}
                        <div class="w-full space-y-2 xl:max-w-md">
                            <label for="department-select"
                                class="flex items-center gap-2 text-xs font-semibold
                               uppercase tracking-wider text-gray-600
                               dark:text-gray-400">
                                <x-heroicon-o-building-office-2 class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                Department / Unit Kerja
                            </label>

                            <x-filament::input.wrapper>
                                <x-filament::input.select id="department-select" wire:model.live="selectedDepartmentId">
                                    @forelse ($depts as $dept)
                                        @php
                                            $status = strtoupper($dept->rka_status ?? 'DRAFT');

                                            $statusLabel = match ($status) {
                                                'APPROVED' => 'Disetujui',
                                                'IN_APPROVED' => 'Menunggu Pengesahan',
                                                'IN_REVIEW' => 'Dalam Review',
                                                'REVISION_REQUIRED' => 'Perlu Revisi',
                                                'SUBMITTED' => 'Diajukan',
                                                default => 'Draft',
                                            };
                                        @endphp

                                        <option value="{{ $dept->id }}">
                                            {{ $dept->name }} — {{ $statusLabel }}
                                        </option>
                                    @empty
                                        <option value="">Tidak ada department tersedia</option>
                                    @endforelse
                                </x-filament::input.select>
                            </x-filament::input.wrapper>

                            <p class="text-xs text-gray-500 dark:text-gray-500">
                                Pilih unit kerja untuk melihat detail pengajuan RKA.
                            </p>
                        </div>

                        {{-- Status statistics --}}
                        <div class="min-w-0 space-y-2">
                            <p
                                class="text-xs font-semibold uppercase tracking-wider
                               text-gray-500 dark:text-gray-400">
                                Ringkasan Status Pengajuan
                            </p>

                            <div class="flex flex-wrap gap-2">
                                @if ($isWadir)
                                    {{-- Submitted --}}
                                    <div
                                        class="inline-flex items-center gap-2 rounded-lg border
                                       border-cyan-200 bg-cyan-50 px-3 py-2
                                       dark:border-cyan-500/20 dark:bg-cyan-500/10">
                                        <span class="h-2 w-2 rounded-full bg-cyan-500"></span>
                                        <span class="text-xs text-cyan-800 dark:text-cyan-300">
                                            Diajukan
                                        </span>
                                        <span class="text-sm font-bold tabular-nums text-cyan-900 dark:text-cyan-200">
                                            {{ $submittedCount }}
                                        </span>
                                    </div>

                                    {{-- In Review --}}
                                    <div
                                        class="inline-flex items-center gap-2 rounded-lg border
                                       border-amber-200 bg-amber-50 px-3 py-2
                                       dark:border-amber-500/20 dark:bg-amber-500/10">
                                        <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                                        <span class="text-xs text-amber-800 dark:text-amber-300">
                                            Dalam Review
                                        </span>
                                        <span class="text-sm font-bold tabular-nums text-amber-900 dark:text-amber-200">
                                            {{ $inReviewCount }}
                                        </span>
                                    </div>

                                    {{-- Revision Required --}}
                                    <div
                                        class="inline-flex items-center gap-2 rounded-lg border
                                       border-rose-200 bg-rose-50 px-3 py-2
                                       dark:border-rose-500/20 dark:bg-rose-500/10">
                                        <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                                        <span class="text-xs text-rose-800 dark:text-rose-300">
                                            Perlu Revisi
                                        </span>
                                        <span class="text-sm font-bold tabular-nums text-rose-900 dark:text-rose-200">
                                            {{ $revisionCount }}
                                        </span>
                                    </div>
                                @elseif ($isDirektur)
                                    {{-- In Approved --}}
                                    <div
                                        class="inline-flex items-center gap-2 rounded-lg border
                                       border-blue-200 bg-blue-50 px-3 py-2
                                       dark:border-blue-500/20 dark:bg-blue-500/10">
                                        <span class="h-2 w-2 rounded-full bg-blue-500"></span>
                                        <span class="text-xs text-blue-800 dark:text-blue-300">
                                            Siap Disahkan
                                        </span>
                                        <span class="text-sm font-bold tabular-nums text-blue-900 dark:text-blue-200">
                                            {{ $inApprovedCount }}
                                        </span>
                                    </div>

                                    {{-- Approved --}}
                                    <div
                                        class="inline-flex items-center gap-2 rounded-lg border
                                       border-emerald-200 bg-emerald-50 px-3 py-2
                                       dark:border-emerald-500/20 dark:bg-emerald-500/10">
                                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                        <span class="text-xs text-emerald-800 dark:text-emerald-300">
                                            Disahkan
                                        </span>
                                        <span
                                            class="text-sm font-bold tabular-nums text-emerald-900 dark:text-emerald-200">
                                            {{ $approvedCount }}
                                        </span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </section>
            @endif

            {{-- 2. NAVIGASI SHEET TABS --}}
            <section
                class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm
               dark:border-gray-700 dark:bg-gray-900">
                {{-- Tab header --}}
                <div class="overflow-x-auto">
                    <nav class="flex min-w-max items-end gap-0 border-b border-gray-200 px-2 pt-2
                       dark:border-gray-700 sm:px-3"
                        role="tablist" aria-label="Navigasi Review RKA">
                        {{-- Ringkasan --}}
                        <button type="button" role="tab"
                            aria-selected="{{ $activeTab === 'ringkasan' ? 'true' : 'false' }}"
                            wire:click="setActiveTab('ringkasan')" @class([
                                'relative inline-flex min-h-11 items-center gap-2 whitespace-nowrap',
                                'border border-b-0 px-4 py-3 text-sm transition-colors',
                                'rounded-t-lg focus:outline-none focus-visible:ring-2',
                                'focus-visible:ring-inset focus-visible:ring-blue-500',
                                'font-semibold' => $activeTab === 'ringkasan',
                                'border-gray-200 bg-white text-blue-700',
                                'dark:border-gray-700 dark:bg-gray-900 dark:text-blue-400' =>
                                    $activeTab === 'ringkasan',
                                'border-transparent bg-gray-50 text-gray-600',
                                'hover:bg-gray-100 hover:text-gray-900',
                                'dark:bg-gray-800/50 dark:text-gray-400',
                                'dark:hover:bg-gray-800 dark:hover:text-gray-100' =>
                                    $activeTab !== 'ringkasan',
                            ])>
                            <x-heroicon-o-chart-bar class="h-4 w-4 shrink-0" />
                            <span>Ringkasan Review</span>

                            @if ($activeTab === 'ringkasan')
                                <span
                                    class="absolute inset-x-0 top-0 h-0.5 rounded-t-lg bg-blue-600 dark:bg-blue-400"></span>
                            @endif
                        </button>

                        {{-- Sheet RKA --}}
                        <button type="button" role="tab"
                            aria-selected="{{ $activeTab === 'sheet_rka' ? 'true' : 'false' }}"
                            wire:click="setActiveTab('sheet_rka')" @class([
                                'relative inline-flex min-h-11 items-center gap-2 whitespace-nowrap',
                                'border border-b-0 px-4 py-3 text-sm transition-colors',
                                'rounded-t-lg focus:outline-none focus-visible:ring-2',
                                'focus-visible:ring-inset focus-visible:ring-blue-500',
                                'font-semibold' => $activeTab === 'sheet_rka',
                                'border-gray-200 bg-white text-blue-700',
                                'dark:border-gray-700 dark:bg-gray-900 dark:text-blue-400' =>
                                    $activeTab === 'sheet_rka',
                                'border-transparent bg-gray-50 text-gray-600',
                                'hover:bg-gray-100 hover:text-gray-900',
                                'dark:bg-gray-800/50 dark:text-gray-400',
                                'dark:hover:bg-gray-800 dark:hover:text-gray-100' =>
                                    $activeTab !== 'sheet_rka',
                            ])>
                            <x-heroicon-o-document-text class="h-4 w-4 shrink-0" />
                            <span>Sheet RKA</span>

                            @if ($activeTab === 'sheet_rka')
                                <span
                                    class="absolute inset-x-0 top-0 h-0.5 rounded-t-lg bg-blue-600 dark:bg-blue-400"></span>
                            @endif
                        </button>

                        {{-- Penyesuaian --}}
                        <button type="button" role="tab"
                            aria-selected="{{ $activeTab === 'penyesuaian' ? 'true' : 'false' }}"
                            wire:click="setActiveTab('penyesuaian')" @class([
                                'relative inline-flex min-h-11 items-center gap-2 whitespace-nowrap',
                                'border border-b-0 px-4 py-3 text-sm transition-colors',
                                'rounded-t-lg focus:outline-none focus-visible:ring-2',
                                'focus-visible:ring-inset focus-visible:ring-blue-500',
                                'font-semibold' => $activeTab === 'penyesuaian',
                                'border-gray-200 bg-white text-blue-700',
                                'dark:border-gray-700 dark:bg-gray-900 dark:text-blue-400' =>
                                    $activeTab === 'penyesuaian',
                                'border-transparent bg-gray-50 text-gray-600',
                                'hover:bg-gray-100 hover:text-gray-900',
                                'dark:bg-gray-800/50 dark:text-gray-400',
                                'dark:hover:bg-gray-800 dark:hover:text-gray-100' =>
                                    $activeTab !== 'penyesuaian',
                            ])>
                            <x-heroicon-o-arrows-right-left class="h-4 w-4 shrink-0" />
                            <span>Daftar Penyesuaian</span>

                            @if ($activeTab === 'penyesuaian')
                                <span
                                    class="absolute inset-x-0 top-0 h-0.5 rounded-t-lg bg-blue-600 dark:bg-blue-400"></span>
                            @endif
                        </button>

                        {{-- Rekapitulasi --}}
                        <button type="button" role="tab"
                            aria-selected="{{ $activeTab === 'rekap' ? 'true' : 'false' }}"
                            wire:click="setActiveTab('rekap')" @class([
                                'relative inline-flex min-h-11 items-center gap-2 whitespace-nowrap',
                                'border border-b-0 px-4 py-3 text-sm transition-colors',
                                'rounded-t-lg focus:outline-none focus-visible:ring-2',
                                'focus-visible:ring-inset focus-visible:ring-blue-500',
                                'font-semibold' => $activeTab === 'rekap',
                                'border-gray-200 bg-white text-blue-700',
                                'dark:border-gray-700 dark:bg-gray-900 dark:text-blue-400' =>
                                    $activeTab === 'rekap',
                                'border-transparent bg-gray-50 text-gray-600',
                                'hover:bg-gray-100 hover:text-gray-900',
                                'dark:bg-gray-800/50 dark:text-gray-400',
                                'dark:hover:bg-gray-800 dark:hover:text-gray-100' => $activeTab !== 'rekap',
                            ])>
                            <x-heroicon-o-calculator class="h-4 w-4 shrink-0" />
                            <span>Rekapitulasi RKA</span>

                            @if ($activeTab === 'rekap')
                                <span
                                    class="absolute inset-x-0 top-0 h-0.5 rounded-t-lg bg-blue-600 dark:bg-blue-400"></span>
                            @endif
                        </button>

                        {{-- Riwayat Aktivitas --}}
                        <button type="button" role="tab"
                            aria-selected="{{ $activeTab === 'riwayat' ? 'true' : 'false' }}"
                            wire:click="setActiveTab('riwayat')" @class([
                                'relative inline-flex min-h-11 items-center gap-2 whitespace-nowrap',
                                'border border-b-0 px-4 py-3 text-sm transition-colors',
                                'rounded-t-lg focus:outline-none focus-visible:ring-2',
                                'focus-visible:ring-inset focus-visible:ring-blue-500',
                                'font-semibold' => $activeTab === 'riwayat',
                                'border-gray-200 bg-white text-blue-700',
                                'dark:border-gray-700 dark:bg-gray-900 dark:text-blue-400' =>
                                    $activeTab === 'riwayat',
                                'border-transparent bg-gray-50 text-gray-600',
                                'hover:bg-gray-100 hover:text-gray-900',
                                'dark:bg-gray-800/50 dark:text-gray-400',
                                'dark:hover:bg-gray-800 dark:hover:text-gray-100' => $activeTab !== 'riwayat',
                            ])>
                            <x-heroicon-o-clock class="h-4 w-4 shrink-0" />
                            <span>Riwayat Aktivitas</span>

                            @if ($activeTab === 'riwayat')
                                <span
                                    class="absolute inset-x-0 top-0 h-0.5 rounded-t-lg bg-blue-600 dark:bg-blue-400"></span>
                            @endif
                        </button>
                    </nav>
                </div>

                {{-- Ruang konten sheet mengikuti tema --}}
                <div class="bg-white dark:bg-gray-900"></div>

                @php
                    // Ambil status RKA dari department yang sedang aktif
                    $currentDepartmentStatus = $this->record->status ?? 'submitted';
                    $isCurrentSubmitted = $currentDepartmentStatus === 'IN_REVIEW';
                @endphp

                @php
                    $user = auth()->user();
                    $status = strtoupper($record->rka_status ?? ($record->status ?? 'DRAFT'));
                    $deptName = $record->department->name ?? 'Unit Work';
                @endphp

                {{-- =========================================================================
                1. BANNER PERINGATAN (Sesuai Role & Status Transisi)
            ========================================================================= --}}

                {{-- ALERT UNTUK PEMOHON / UNIT WORK --}}
                @if ($user->hasRole('pemohon'))
                    @if ($status === 'DRAFT')
                        <div
                            class="mb-6 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-amber-200 shadow-lg backdrop-blur-sm">
                            <div class="flex items-start gap-3">
                                <div class="rounded-lg bg-amber-500/20 p-2 text-amber-400">
                                    <x-heroicon-o-exclamation-triangle class="h-6 w-6" />
                                </div>
                                <div class="flex-1 space-y-1">
                                    <div class="flex items-center justify-between">
                                        <h4 class="text-sm font-bold text-amber-300">
                                            RKA Masih Berstatus Draft
                                        </h4>
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full bg-amber-500/20 px-2.5 py-0.5 text-xs font-semibold text-amber-400 border border-amber-500/40">
                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                                            DRAFT
                                        </span>
                                    </div>
                                    <p class="text-xs text-amber-200/80 leading-relaxed">
                                        Data indikator dan item usulan anggaran untuk <span
                                            class="font-semibold text-amber-300 underline">{{ $deptName }}</span>
                                        masih
                                        dalam tahap penyusunan. Pastikan nominal Pagu tidak 0 sebelum melakukan
                                        pengajuan
                                        (Submit RKA).
                                    </p>
                                </div>
                            </div>
                        </div>
                    @elseif ($status === 'REVISION_REQUIRED')
                        <div
                            class="mb-6 rounded-xl border border-rose-500/30 bg-rose-500/10 p-4 text-rose-200 shadow-lg backdrop-blur-sm">
                            <div class="flex items-start gap-3">
                                <div class="rounded-lg bg-rose-500/20 p-2 text-rose-400">
                                    {{-- <x-heroicon-o-arrow-path-arrow-up-right class="h-6 w-6" /> --}}
                                </div>
                                <div class="flex-1 space-y-1">
                                    <div class="flex items-center justify-between">
                                        <h4 class="text-sm font-bold text-rose-300">
                                            RKA Memerlukan Revisi Penyesuaian
                                        </h4>
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full bg-rose-500/20 px-2.5 py-0.5 text-xs font-semibold text-rose-400 border border-rose-500/40">
                                            REVISION REQUIRED
                                        </span>
                                    </div>
                                    <p class="text-xs text-rose-200/80 leading-relaxed">
                                        RKA unit kerja ini telah ditelaah dan terdapat catatan revisi. Silakan periksa
                                        rekomendasi penyesuaian pada tabel review di bawah.
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- ALERT UNTUK WADIR KEUANGAN --}}
                @elseif ($user->hasRole('wadir_keuangan'))
                    @if (in_array($status, ['DRAFT', 'SUBMITTED']))
                        <div
                            class="mb-6 rounded-xl border border-cyan-500/30 bg-cyan-500/10 p-4 text-cyan-200 shadow-lg backdrop-blur-sm">
                            <div class="flex items-start gap-3">
                                <div class="rounded-lg bg-cyan-500/20 p-2 text-cyan-400">
                                    <x-heroicon-o-information-circle class="h-6 w-6" />
                                </div>
                                <div class="flex-1 space-y-1">
                                    <div class="flex items-center justify-between">
                                        <h4 class="text-sm font-bold text-cyan-300">
                                            Pengajuan RKA Masuk ({{ $status }})
                                        </h4>
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full bg-cyan-500/20 px-2.5 py-0.5 text-xs font-semibold text-cyan-400 border border-cyan-500/40">
                                            {{ $status }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-cyan-200/80 leading-relaxed">
                                        RKA dari unit <span
                                            class="font-semibold text-cyan-300 underline">{{ $deptName }}</span>
                                        telah
                                        masuk ke antrean Wadir Keuangan. Silakan lakukan pemeriksaan indikator dan
                                        rincian
                                        item sebelum meneruskannya ke Direktur.
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- ALERT UNTUK DIREKTUR --}}
                @elseif ($user->hasRole('direktur'))
                    @if ($status === 'IN_APPROVED')
                        <div
                            class="mb-6 rounded-xl border border-blue-500/30 bg-blue-500/10 p-4 text-blue-200 shadow-lg backdrop-blur-sm">
                            <div class="flex items-start gap-3">
                                <div class="rounded-lg bg-blue-500/20 p-2 text-blue-400">
                                    <x-heroicon-o-check-badge class="h-6 w-6" />
                                </div>
                                <div class="flex-1 space-y-1">
                                    <div class="flex items-center justify-between">
                                        <h4 class="text-sm font-bold text-blue-300">
                                            RKA Siap Disahkan (In Approved)
                                        </h4>
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full bg-blue-500/20 px-2.5 py-0.5 text-xs font-semibold text-blue-400 border border-blue-500/40">
                                            IN APPROVED
                                        </span>
                                    </div>
                                    <p class="text-xs text-blue-200/80 leading-relaxed">
                                        RKA unit <span
                                            class="font-semibold text-blue-300 underline">{{ $deptName }}</span>
                                        telah
                                        diverifikasi dan disetujui oleh Wadir Keuangan. Silakan periksa ringkasan hasil
                                        review di bawah untuk keputusan pengesahan akhir (APPROVED) atau kembalikan
                                        untuk
                                        revisi.
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif
                @endif

                <!-- ================================================================= -->
                <!-- TAB 1: RINGKASAN HASIL REVIEW -->
                <!-- ================================================================= -->
                @if ($activeTab === 'ringkasan')
                    @include('filament.resources.rka-headers.components.budget-summary', [
                        'summary' => $this->summaryData,
                        'rekap' => $this->rekapByKelompok,
                    ])
                @endif

                <!-- ================================================================= -->
                <!-- TAB 2: SHEET RKA (DETAIL REVIEW PER ITEM) -->
                <!-- ================================================================= -->
                @if ($activeTab === 'sheet_rka')
                    <div class="space-y-4" x-data="{
                        selectAll: false,
                        toggleAll(event, items) {
                            this.selectAll = event.target.checked;
                            $wire.set('selectedItems', this.selectAll ? items : []);
                        }
                    }">

                        {{-- Toolbar Indikator RKA --}}
                        <div class="space-y-4">

                            {{-- Indicator Sheet Tabs --}}
                            <div class="w-full">
                                <div class="overflow-x-auto">
                                    <div class="flex min-w-max items-end gap-1 border-b border-gray-200 dark:border-gray-700"
                                        role="tablist" aria-label="Indikator RKA">
                                        @foreach ($record->indicators as $indicator)
                                            @php
                                                $isActive = (int) $activeIndicatorTab === (int) $indicator['id'];
                                                $itemCount = $indicator->items()->count();
                                            @endphp

                                            <button type="button" role="tab"
                                                aria-selected="{{ $isActive ? 'true' : 'false' }}"
                                                wire:click="setActiveIndicatorTab('{{ $indicator['id'] }}')"
                                                @class([
                                                    'group relative inline-flex min-h-10 items-center gap-2',
                                                    'rounded-t-md border border-b-0 px-4 py-2',
                                                    'text-sm font-medium whitespace-nowrap',
                                                    'transition-colors duration-150',
                                                    'focus:outline-none focus-visible:ring-2',
                                                    'focus-visible:ring-inset focus-visible:ring-blue-500',
                                                
                                                    // Active tab
                                                    'z-10 -mb-px border-gray-200 bg-white text-blue-700' => $isActive,
                                                    'dark:border-gray-700 dark:bg-gray-900 dark:text-blue-400' => $isActive,
                                                
                                                    // Inactive tab
                                                    'border-transparent bg-gray-100 text-gray-600' => !$isActive,
                                                    'hover:border-gray-200 hover:bg-gray-50 hover:text-gray-900' => !$isActive,
                                                    'dark:bg-gray-800 dark:text-gray-400' => !$isActive,
                                                    'dark:hover:border-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-100' => !$isActive,
                                                ])>
                                                {{-- Indicator status --}}
                                                <span @class([
                                                    'h-2 w-2 shrink-0 rounded-full transition-colors',
                                                    'bg-blue-600 dark:bg-blue-400' => $isActive,
                                                    'bg-gray-400 group-hover:bg-gray-500 dark:bg-gray-500' => !$isActive,
                                                ])></span>

                                                {{-- Activity name --}}
                                                <span>
                                                    {{ $indicator['activity_name'] }}
                                                </span>

                                                {{-- Item count from indicators.items relation --}}
                                                <span @class([
                                                    'inline-flex min-w-6 items-center justify-center',
                                                    'rounded px-1.5 py-0.5 text-xs font-semibold tabular-nums',
                                                    'bg-blue-100 text-blue-700',
                                                    'dark:bg-blue-950 dark:text-blue-300' => $isActive,
                                                    'bg-gray-200 text-gray-600',
                                                    'dark:bg-gray-700 dark:text-gray-300' => !$isActive,
                                                ])>
                                                    {{ number_format($itemCount, 0, ',', '.') }}
                                                </span>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>

                                {{-- Worksheet content separator --}}
                                <div class="h-px bg-white dark:bg-gray-900"></div>
                            </div>

                            {{-- Baris 2: Filter tampilan --}}
                            <div
                                class="px-2 flex flex-col gap-3 border-t border-gray-200 pt-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">

                                <div>
                                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                                        Tampilan Item Anggaran
                                    </h3>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Atur item yang ditampilkan dalam daftar RKA.
                                    </p>
                                </div>

                                <div
                                    class="inline-flex w-full flex-col gap-1 rounded-xl border border-gray-200 bg-gray-100 p-1 dark:border-gray-700 dark:bg-gray-900 sm:w-auto sm:flex-row">

                                    {{-- Item final disahkan --}}
                                    <button type="button" wire:click="toggleShowAllItems(false)"
                                        @class([
                                            'inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-xs font-semibold transition-all duration-200',
                                            'bg-white text-amber-700 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:text-amber-300 dark:ring-gray-700' => !$showAllItems,
                                            'text-gray-500 hover:bg-white/70 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200' => $showAllItems,
                                        ])
                                        @if (!$showAllItems) aria-pressed="true"
                @else
                    aria-pressed="false" @endif>
                                        <x-heroicon-m-check-badge class="h-4 w-4" />
                                        <span>Item Final Disahkan</span>
                                    </button>

                                    {{-- Semua item --}}
                                    <button type="button" wire:click="toggleShowAllItems(true)"
                                        @class([
                                            'inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-xs font-semibold transition-all duration-200',
                                            'bg-white text-amber-700 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:text-amber-300 dark:ring-gray-700' => $showAllItems,
                                            'text-gray-500 hover:bg-white/70 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200' => !$showAllItems,
                                        ])
                                        @if ($showAllItems) aria-pressed="true"
                @else
                    aria-pressed="false" @endif>
                                        <x-heroicon-m-list-bullet class="h-4 w-4" />
                                        <span>Tampilkan Semua Item</span>
                                    </button>
                                </div>
                            </div>

                        </div>


                        @php
                            $data = $this->budgetHierarchy;
                            // Kumpulkan seluruh ID item untuk fitur Select All
                            $allItemIds = collect($data['itemsByAccount'] ?? [])
                                ->flatten()
                                ->pluck('id')
                                ->toArray();
                        @endphp
                        <x-rka-header-account :record="$record" :activeTabGroup="$activeIndicatorTab" :isAllItems="$showAllItems">

                            @include('filament.resources.rka-headers.components.account-table', [
                                'mode' => 'review',
                                'data' => $data,
                                'allItemIds' => $allItemIds,
                            ])
                        </x-rka-header-account>
                    </div>
                @endif

                <!-- ================================================================= -->
                <!-- TAB 3: DAFTAR PENYESUAIAN USULAN -->
                <!-- ================================================================= -->
                @if ($activeTab === 'penyesuaian')
                    @include('filament.resources.rka-headers.components.adjustment-sections', [
                        'record' => $record,
                    ])
                @endif

                <!-- ================================================================= -->
                <!-- TAB 4: REKAPITULASI RKA & KONTROL PAGU -->
                <!-- ================================================================= -->
                @if ($activeTab === 'rekap')
                    @include('filament.resources.rka-headers.components.budget-review', [
                        'summary' => $this->summaryAccountHierarchy,
                        'kontrol' => $this->kontrolPaguData,
                    ])
                @endif

                <!-- ================================================================= -->
                <!-- TAB 5: RIWAYAT AKTIVITAS RKA -->
                <!-- ================================================================= -->
                @if ($activeTab === 'riwayat')
                    @include('filament.resources.rka-headers.components.activity-history', [
                        'logs' => $this->activityLogs,
                    ])
                @endif

            </section>

        </div>


    </div>
</x-filament-panels::page>
