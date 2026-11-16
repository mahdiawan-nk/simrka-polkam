@php
    $branding = app(\App\Services\Branding\BrandSettingsService::class);
    $brandName = $branding->getBrandName();
    $shortName = $branding->getInstitutionShortName();
    $appFullName = $branding->getAppFullName();
    $logoLight = $branding->getLogoUrl('light');
    $logoDark = $branding->getLogoUrl('dark');
    $logoPrimary = $branding->getLogoUrl('primary');
    $hasCustomLight = $logoLight !== null;
    $hasCustomDark = $logoDark !== null;
@endphp

<div class="flex items-center py-1 select-none">
    <a href="{{ filament()->getUrl() }}" class="group relative flex items-center gap-3 focus:outline-none">

        <!-- LOGO CONTAINER -->
        @if ($hasCustomLight || $hasCustomDark || $logoPrimary)
            <div class="relative flex h-10 shrink-0 items-center justify-center">
                @if ($hasCustomLight && $hasCustomDark && $logoLight !== $logoDark)
                    <img src="{{ $logoLight }}" alt="{{ $brandName }}" class="h-10 w-auto max-w-[140px] object-contain dark:hidden">
                    <img src="{{ $logoDark }}" alt="{{ $brandName }}" class="hidden h-10 w-auto max-w-[140px] object-contain dark:block">
                @else
                    <img src="{{ $logoLight ?? $logoDark ?? $logoPrimary }}" alt="{{ $brandName }}" class="h-10 w-auto max-w-[140px] object-contain">
                @endif
            </div>
        @else
            <div
                class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-primary dark:bg-brand-secondary shadow-sm ring-1 ring-brand-primary/15 dark:ring-white/15 transition-colors duration-200 group-hover:bg-brand-secondary">
                <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <span
                    class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full border-2 border-white dark:border-slate-900 bg-brand-highlight"></span>
            </div>
        @endif

        <!-- BRAND TEXT & DETAILS -->
        <div class="relative flex flex-col justify-center">
            <div class="flex items-center gap-2">
                <span class="text-lg font-extrabold tracking-tight text-brand-primary dark:text-white">
                    {{ $brandName }}
                </span>
                @if ($shortName !== '')
                    <span
                        class="inline-flex items-center rounded border border-brand-highlight/40 bg-brand-highlight/10 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-brand-primary dark:text-brand-highlight">
                        {{ $shortName }}
                    </span>
                @endif
            </div>
            <span class="text-[10px] font-semibold tracking-wider uppercase text-slate-500 dark:text-slate-400">
                {{ $appFullName }}
            </span>
        </div>

    </a>
</div>
