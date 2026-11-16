<x-filament-panels::page>
    @php
        $preview = $this->getPreviewData();
        $draft = $preview['draft'];
        $active = $preview['active'];
    @endphp

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
        {{-- Form Pengaturan --}}
        <div class="xl:col-span-7">
            <form wire:submit="save" class="space-y-6">
                {{ $this->form }}

                <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                    <x-filament::button type="submit" icon="heroicon-m-check">
                        Simpan Perubahan
                    </x-filament::button>
                </div>
            </form>
        </div>

        {{-- Panel Live Preview --}}
        <div class="space-y-5 xl:col-span-5">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-4 flex items-center justify-between border-b border-slate-100 pb-3 dark:border-slate-800">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                            Pratinjau Sementara (Belum Disimpan)
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Tampilan otomatis diperbarui saat Anda mengubah kolom form.
                        </p>
                    </div>
                    <span class="inline-flex items-center rounded-md bg-brand-highlight/15 px-2 py-0.5 text-[11px] font-semibold text-brand-primary dark:text-amber-300">
                        Pratinjau
                    </span>
                </div>

                <div class="space-y-4">
                    {{-- 1. Preview Tab Browser --}}
                    <div>
                        <div class="mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            Tab Browser &amp; Favicon
                        </div>
                        <div class="flex items-center gap-2.5 rounded-t-xl border border-slate-200 bg-slate-100 px-3 py-2 dark:border-slate-700 dark:bg-slate-800">
                            <div class="flex items-center gap-2 rounded-lg bg-white px-3 py-1.5 shadow-xs dark:bg-slate-900">
                                @if ($draft['favicon_url'])
                                    <img src="{{ $draft['favicon_url'] }}" alt="Preview Favicon" class="h-4 w-4 rounded-xs object-contain">
                                @else
                                    <span class="inline-flex h-4 w-4 items-center justify-center rounded-xs bg-brand-primary text-[9px] font-bold text-white">e</span>
                                @endif
                                <span class="max-w-[220px] truncate text-xs font-medium text-slate-800 dark:text-slate-200">
                                    {{ $draft['browser_title'] }}
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- 2. Preview Header / Topbar --}}
                    <div>
                        <div class="mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            Header Aplikasi
                        </div>
                        <div class="relative overflow-hidden rounded-xl border border-slate-200 bg-white p-3.5 dark:border-slate-700 dark:bg-slate-950">
                            <div class="absolute inset-x-0 top-0 h-[2.5px] bg-gradient-to-r from-brand-primary via-brand-secondary to-brand-highlight"></div>
                            <div class="flex items-center justify-between pt-1">
                                <div class="flex items-center gap-3">
                                    @if ($draft['logo_url'])
                                        <img src="{{ $draft['logo_url'] }}" alt="Preview Logo" class="h-9 w-auto max-w-[120px] object-contain">
                                    @else
                                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-primary text-white">
                                            <x-heroicon-o-chart-bar class="h-5 w-5" />
                                        </div>
                                    @endif
                                    <div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-sm font-extrabold text-brand-primary dark:text-white">{{ $draft['brand_name'] }}</span>
                                            <span class="rounded border border-brand-highlight/40 bg-brand-highlight/10 px-1.5 py-0.5 text-[9px] font-bold uppercase text-brand-primary dark:text-amber-300">
                                                {{ $draft['institution_short_name'] }}
                                            </span>
                                        </div>
                                        <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                            {{ $draft['app_full_name'] }}
                                        </div>
                                    </div>
                                </div>
                                <div class="h-7 w-7 rounded-full bg-slate-200 dark:bg-slate-800"></div>
                            </div>
                        </div>
                    </div>

                    {{-- 3. Preview Compact / Sidebar --}}
                    <div>
                        <div class="mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            Logo Compact (Sidebar / Mobile)
                        </div>
                        <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 dark:border-slate-700 dark:bg-slate-800/60">
                            @if ($draft['compact_logo_url'])
                                <img src="{{ $draft['compact_logo_url'] }}" alt="Preview Compact Logo" class="h-8 w-8 rounded-lg object-contain">
                            @else
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-primary text-xs font-bold text-white">
                                    {{ strtoupper(substr($draft['brand_name'], 0, 2)) }}
                                </div>
                            @endif
                            <div class="text-xs font-semibold text-slate-700 dark:text-slate-200">
                                {{ $draft['institution_name'] }}
                            </div>
                        </div>
                    </div>

                    {{-- 4. Preview Login Page Mini --}}
                    <div>
                        <div class="mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            Halaman Masuk (Login)
                        </div>
                        <div class="grid grid-cols-12 overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                            <div class="col-span-6 bg-brand-primary p-3.5 text-white">
                                @if ($draft['login_logo_url'])
                                    <img src="{{ $draft['login_logo_url'] }}" alt="Preview Login Logo" class="mb-2 h-7 w-auto object-contain">
                                @endif
                                <div class="text-xs font-bold">{{ $draft['brand_name'] }}</div>
                                <div class="text-[10px] text-slate-200">{{ $draft['institution_name'] }}</div>
                                <div class="mt-2 text-[9px] text-slate-300 line-clamp-2">{{ $draft['tagline'] }}</div>
                            </div>
                            <div class="col-span-6 bg-white p-3.5 dark:bg-slate-900">
                                <div class="text-[11px] font-bold text-slate-900 dark:text-white">Masuk ke Akun</div>
                                <div class="mt-2 h-4 rounded bg-slate-100 dark:bg-slate-800"></div>
                                <div class="mt-1.5 h-4 rounded bg-slate-100 dark:bg-slate-800"></div>
                                <div class="mt-2.5 h-5 rounded bg-brand-primary"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Identitas Aktif Saat Ini --}}
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/60">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300">
                        Identitas Aktif Saat Ini
                    </span>
                    <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                        Tersimpan
                    </span>
                </div>
                <dl class="mt-2.5 space-y-1 text-xs text-slate-600 dark:text-slate-400">
                    <div class="flex justify-between gap-2">
                        <dt>Brand:</dt>
                        <dd class="font-semibold text-slate-900 dark:text-white">{{ $active['brand_name'] }} ({{ $active['institution_short_name'] }})</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt>Institusi:</dt>
                        <dd class="font-semibold text-slate-900 dark:text-white">{{ $active['institution_name'] }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt>Judul Browser:</dt>
                        <dd class="truncate font-medium text-slate-800 dark:text-slate-200">{{ $active['browser_title'] }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</x-filament-panels::page>
