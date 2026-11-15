<div class="flex items-center py-1 select-none">
    <a href="{{ filament()->getUrl() }}" class="group relative flex items-center gap-3.5 focus:outline-none">

        <!-- LOGO CONTAINER -->
        <div
            class="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-slate-900 via-emerald-950 to-slate-900 dark:from-emerald-600 dark:via-teal-700 dark:to-slate-900 p-[1px] shadow-xl shadow-emerald-900/10 dark:shadow-emerald-500/20 ring-1 ring-slate-900/10 dark:ring-white/20 transition-all duration-300 group-hover:scale-105 group-hover:shadow-emerald-500/30">

            <!-- Inner Glass Layer -->
            <div
                class="flex h-full w-full items-center justify-center rounded-[15px] bg-slate-900/90 dark:bg-slate-900/80 backdrop-blur-md">
                <!-- Icon Modern Chart/Calculator (Identitas Anggaran & Keuangan) -->
                <svg class="h-5 w-5 text-emerald-400 drop-shadow-[0_0_8px_rgba(52,211,153,0.5)] transition-transform duration-300 group-hover:scale-110"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
            </div>

            <!-- Mini Indicator Pulse Status -->
            <span class="absolute -right-0.5 -top-0.5 flex h-3 w-3">
                <span
                    class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                <span
                    class="relative inline-flex h-3 w-3 rounded-full border-2 border-white dark:border-slate-900 bg-emerald-500"></span>
            </span>
        </div>

        <!-- BRAND TEXT & DETAILS -->
        <div class="relative flex flex-col justify-center">
            <div class="flex items-center gap-2">
                <!-- Title e-RKA -->
                <span class="text-xl font-black tracking-tight text-slate-900 dark:text-white">
                    <span
                        class="bg-gradient-to-r from-emerald-600 via-teal-500 to-amber-500 dark:from-emerald-400 dark:via-teal-300 dark:to-amber-400 bg-clip-text text-transparent">e-RKA</span>
                </span>

                <!-- Badge PRO Edition -->
                <span
                    class="inline-flex items-center rounded-md border border-emerald-500/20 bg-emerald-500/10 px-1.5 py-0.5 text-[8.5px] font-extrabold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 backdrop-blur-sm">
                    PRO
                </span>
            </div>

            <!-- Subtitle Rencana Kerja & Anggaran -->
            <span class="text-[9.5px] font-bold tracking-wider uppercase text-slate-500 dark:text-slate-400">
                Sistem Perencanaan & Anggaran
            </span>
        </div>

    </a>
</div>
