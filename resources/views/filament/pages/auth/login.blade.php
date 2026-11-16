@php
    $brand = $this->getBrandContext();
@endphp

<div class="min-h-screen w-full overflow-x-hidden bg-canvas text-ink-primary dark:bg-[#0B1220] dark:text-slate-100">
    <div class="grid min-h-screen grid-cols-1 lg:grid-cols-12">

        {{-- Area Branding Institusional (Desktop Kiri / Mobile Compact Header) --}}
        <aside class="relative flex flex-col justify-between overflow-hidden bg-[#17324D] px-6 py-8 text-white sm:px-10 lg:col-span-6 lg:px-14 lg:py-12 xl:col-span-7">
            {{-- Pola Geometris Keuangan Akademik Halus --}}
            <div class="pointer-events-none absolute inset-0 opacity-[0.07]"
                style="background-image: linear-gradient(to right, #ffffff 1px, transparent 1px), linear-gradient(to bottom, #ffffff 1px, transparent 1px); background-size: 40px 40px;">
            </div>
            <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-[#245B85]/40 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-24 -left-24 h-72 w-72 rounded-full bg-[#168C83]/25 blur-3xl"></div>

            {{-- Identitas Utama --}}
            <div class="relative z-10 flex items-center gap-3.5">
                @if ($brand['logo_url'])
                    <img src="{{ $brand['logo_url'] }}"
                        alt="{{ $brand['brand_name'] }}"
                        class="h-11 w-auto max-w-[140px] rounded-xl bg-white/10 p-1.5 object-contain ring-1 ring-white/15">
                @else
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/20">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                @endif

                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-xl font-extrabold tracking-tight text-white">
                            {{ $brand['brand_name'] }}
                        </span>
                        <span class="inline-flex items-center rounded border border-[#C69A45]/50 bg-[#C69A45]/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-[#F3D79B]">
                            {{ $brand['institution_short_name'] }}
                        </span>
                    </div>
                    <p class="text-xs font-medium text-slate-300">
                        {{ $brand['institution_name'] }}
                    </p>
                </div>
            </div>

            {{-- Pesan Institusional (Ringkas di Mobile, Penuh di Desktop) --}}
            <div class="relative z-10 my-8 hidden max-w-xl space-y-5 lg:my-0 lg:block">
                <div class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1 text-xs font-medium text-slate-200">
                    <span class="h-2 w-2 rounded-full bg-[#168C83]"></span>
                    <span>Tata Kelola Keuangan Akademik Terintegrasi</span>
                </div>

                <h1 class="text-3xl font-bold leading-tight tracking-tight text-white xl:text-4xl">
                    {{ $brand['app_full_name'] }}
                </h1>

                <p class="text-sm leading-relaxed text-slate-300 xl:text-base">
                    {{ $brand['short_description'] }}
                </p>

                {{-- Indikator Pilar Keuangan Abstrak --}}
                <div class="grid grid-cols-3 gap-3 pt-4">
                    <div class="rounded-xl border border-white/10 bg-white/5 p-3.5">
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-[#F3D79B]">Perencanaan</div>
                        <div class="mt-1 text-xs text-slate-200">Penyusunan &amp; tinjauan RKA terstruktur</div>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-white/5 p-3.5">
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-[#7BE0D6]">Pengendalian</div>
                        <div class="mt-1 text-xs text-slate-200">Monitoring pagu &amp; realisasi real-time</div>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-white/5 p-3.5">
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-200">Akuntabilitas</div>
                        <div class="mt-1 text-xs text-slate-200">Alur verifikasi &amp; persetujuan berjenjang</div>
                    </div>
                </div>
            </div>

            {{-- Footer Area Branding --}}
            <div class="relative z-10 hidden items-center justify-between border-t border-white/10 pt-5 text-xs text-slate-300 lg:flex">
                <span>{{ $brand['tagline'] }}</span>
                <span class="font-medium text-slate-200">&copy; {{ date('Y') }} {{ $brand['institution_name'] }}</span>
            </div>
        </aside>

        {{-- Area Autentikasi (Form Login) --}}
        <main class="flex items-center justify-center px-4 py-8 sm:px-8 lg:col-span-6 lg:px-12 xl:col-span-5">
            <div class="w-full max-w-md">
                <div class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm sm:p-8 dark:border-slate-800 dark:bg-[#111C2E]">
                    <div class="mb-6 border-b border-slate-100 pb-5 dark:border-slate-800">
                        <h2 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">
                            {{ $this->getHeading() }}
                        </h2>
                        <p class="mt-1.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                            {{ $this->getSubheading() }}
                        </p>
                    </div>

                    <div class="fi-simple-page">
                        {{ $this->content }}
                    </div>
                </div>

                <p class="mt-6 text-center text-xs text-slate-500 dark:text-slate-400 lg:hidden">
                    &copy; {{ date('Y') }} {{ $brand['institution_name'] }} &mdash; {{ $brand['brand_name'] }}
                </p>
            </div>
        </main>

    </div>
</div>
