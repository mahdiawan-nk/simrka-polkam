<x-filament-panels::page>
    <div class="w-full min-w-0">
        @if ($mode === 'detail')
            {{-- DETAIL ARTIKEL --}}
            <div class="w-full min-w-0 space-y-6">
                {{-- Breadcrumb & Aksi Header --}}
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-4 dark:border-slate-800">
                    <nav class="flex min-w-0 flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                        <button type="button" wire:click="backToList" class="font-semibold text-brand-primary hover:underline dark:text-blue-400">
                            Pusat Panduan
                        </button>
                        <span>/</span>
                        <span>{{ $article->category?->name }}</span>
                        <span>/</span>
                        <span class="font-medium text-slate-700 dark:text-slate-200">{{ $article->title }}</span>
                    </nav>

                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('knowledge-center.handbook.article', ['slug' => $article->slug]) }}"
                            target="_blank"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-brand-primary/20 bg-brand-primary/5 px-3 py-1.5 text-xs font-semibold text-brand-primary hover:bg-brand-primary/10 dark:border-blue-400/30 dark:bg-blue-500/10 dark:text-blue-300">
                            <x-heroicon-m-arrow-down-tray class="h-4 w-4" />
                            Unduh Artikel PDF
                        </a>

                        <button type="button"
                            wire:click="backToList"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                            <x-heroicon-m-arrow-left class="h-4 w-4" />
                            Kembali
                        </button>
                    </div>
                </div>

                {{-- Daftar Isi Collapsible untuk Tablet & Mobile --}}
                @if (! empty($toc))
                    <div x-data="{ openToc: false }" class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900 lg:hidden">
                        <button type="button"
                            @click="openToc = !openToc"
                            class="flex w-full items-center justify-between text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-200">
                            <span>Daftar Isi Artikel</span>
                            <x-heroicon-m-chevron-down class="h-4 w-4 transition-transform" ::class="openToc ? 'rotate-180' : ''" />
                        </button>
                        <ul x-show="openToc" x-collapse class="mt-3 space-y-1.5 border-t border-slate-100 pt-3 text-xs dark:border-slate-800">
                            @foreach ($toc as $item)
                                <li class="{{ $item['level'] === 3 ? 'pl-3' : '' }}">
                                    <a href="#{{ $item['id'] }}" @click="openToc = false" class="text-slate-600 hover:text-brand-primary dark:text-slate-300">
                                        {{ $item['title'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Komposisi Desktop: 75% Konten Utama (9 col) + 25% Sticky Sidebar (3 col) --}}
                <div class="grid w-full min-w-0 grid-cols-1 gap-6 lg:grid-cols-12">
                    <article class="min-w-0 space-y-6 lg:col-span-9">
                        <div class="w-full min-w-0 rounded-2xl border border-slate-200 bg-white p-6 shadow-xs dark:border-slate-800 dark:bg-slate-900 sm:p-8">
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <span class="inline-flex items-center rounded-md bg-brand-primary/10 px-2.5 py-1 font-semibold text-brand-primary dark:bg-brand-secondary/20 dark:text-blue-300">
                                    {{ $article->category?->name }}
                                </span>
                                <span class="rounded-md bg-slate-100 px-2 py-0.5 font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    {{ $article->roles->isEmpty() ? 'Semua Peran' : $article->roles->pluck('name')->join(', ') }}
                                </span>
                                <span class="text-slate-500 dark:text-slate-400">
                                    &bull; {{ $article->reading_time }} menit baca
                                </span>
                                @if ($article->last_verified_at)
                                    <span class="text-slate-500 dark:text-slate-400">
                                        &bull; Diverifikasi {{ $article->last_verified_at->translatedFormat('d M Y') }}
                                    </span>
                                @endif
                                <span class="text-slate-500 dark:text-slate-400">
                                    &bull; Versi {{ $article->doc_version }}
                                </span>
                            </div>

                            <h1 class="mt-4 text-2xl font-bold leading-snug tracking-tight text-slate-900 dark:text-white sm:text-3xl">
                                {{ $article->title }}
                            </h1>

                            @if ($article->excerpt)
                                <p class="mt-2.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                                    {{ $article->excerpt }}
                                </p>
                            @endif

                            <hr class="my-6 border-slate-200 dark:border-slate-800">

                            <div class="kc-article-content max-w-4xl">
                                {!! $renderedHtml !!}
                            </div>

                            {{-- Diagram Alur Kerja (Workflow) --}}
                            @if (! empty($article->workflow_steps))
                                <div class="mt-8 border-t border-slate-200 pt-6 dark:border-slate-800">
                                    <h2 class="text-sm font-bold uppercase tracking-wider text-brand-primary dark:text-white">
                                        Diagram Alur Kerja
                                    </h2>
                                    <div class="mt-4 flex flex-col gap-3 md:flex-row md:flex-wrap md:items-stretch">
                                        @foreach ($article->workflow_steps as $wfIndex => $wf)
                                            <div class="flex items-center gap-3">
                                                <div class="min-w-[170px] rounded-xl border border-brand-primary/20 bg-slate-50 p-3.5 dark:border-slate-700 dark:bg-slate-800/70">
                                                    <div class="flex items-center gap-2">
                                                        <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-brand-primary text-[11px] font-bold text-white">
                                                            {{ $wfIndex + 1 }}
                                                        </span>
                                                        <span class="text-xs font-bold text-slate-900 dark:text-white">
                                                            {{ $wf['title'] ?? '' }}
                                                        </span>
                                                    </div>
                                                    @if (! empty($wf['actor']))
                                                        <div class="mt-1 text-[11px] font-semibold text-brand-secondary dark:text-blue-300">
                                                            {{ $wf['actor'] }}
                                                        </div>
                                                    @endif
                                                    @if (! empty($wf['note']))
                                                        <div class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                                                            {{ $wf['note'] }}
                                                        </div>
                                                    @endif
                                                </div>
                                                @if (! $loop->last)
                                                    <x-heroicon-m-arrow-right class="hidden h-4 w-4 shrink-0 text-slate-400 md:block" />
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Panduan Visual Langkah demi Langkah --}}
                            @if ($article->steps->isNotEmpty())
                                <div x-data="{ zoomImage: null, zoomCaption: '' }" class="mt-8 space-y-6 border-t border-slate-200 pt-6 dark:border-slate-800">
                                    <h2 class="text-sm font-bold uppercase tracking-wider text-brand-primary dark:text-white">
                                        Panduan Visual Langkah demi Langkah
                                    </h2>

                                    @php $figureCounter = 0; @endphp
                                    @foreach ($article->steps as $stepIndex => $step)
                                        <section class="rounded-2xl border border-slate-200 bg-slate-50/60 p-5 dark:border-slate-800 dark:bg-slate-800/40">
                                            <div class="flex items-start gap-3">
                                                <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-brand-primary text-xs font-bold text-white">
                                                    {{ $stepIndex + 1 }}
                                                </span>
                                                <div class="min-w-0 flex-1">
                                                    <h3 class="text-base font-bold text-slate-900 dark:text-white">
                                                        {{ $step->step_title }}
                                                    </h3>
                                                    @if ($step->step_description)
                                                        <p class="mt-1 text-xs leading-relaxed text-slate-600 dark:text-slate-300">
                                                            {{ $step->step_description }}
                                                        </p>
                                                    @endif
                                                </div>
                                            </div>

                                            @if ($step->image_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($step->image_path))
                                                @php
                                                    $figureCounter++;
                                                    $imgUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($step->image_path);
                                                    $annotations = is_array($step->annotations) ? $step->annotations : [];
                                                @endphp
                                                <figure class="mt-4">
                                                    <div class="relative overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700">
                                                        <img src="{{ $imgUrl }}"
                                                            alt="{{ $step->image_alt ?: $step->step_title }}"
                                                            @click="zoomImage = '{{ $imgUrl }}'; zoomCaption = '{{ addslashes($step->image_caption ?: $step->step_title) }}'"
                                                            class="w-full cursor-zoom-in object-contain" />

                                                        @foreach ($annotations as $ann)
                                                            @php
                                                                $ax = max(0, min(100, (float) ($ann['x'] ?? 0)));
                                                                $ay = max(0, min(100, (float) ($ann['y'] ?? 0)));
                                                                $aw = isset($ann['highlight_w']) && $ann['highlight_w'] !== '' ? (float) $ann['highlight_w'] : null;
                                                                $ah = isset($ann['highlight_h']) && $ann['highlight_h'] !== '' ? (float) $ann['highlight_h'] : null;
                                                            @endphp
                                                            @if ($aw && $ah)
                                                                <div class="pointer-events-none absolute rounded border-2 border-[#C69A45] bg-[#C69A45]/10"
                                                                    style="left: {{ $ax }}%; top: {{ $ay }}%; width: {{ $aw }}%; height: {{ $ah }}%;"></div>
                                                            @endif
                                                            <span class="pointer-events-none absolute -translate-x-1/2 -translate-y-1/2 inline-flex h-6 w-6 items-center justify-center rounded-full border-2 border-white bg-[#17324D] text-[11px] font-bold text-white shadow"
                                                                style="left: {{ $ax }}%; top: {{ $ay }}%;">
                                                                {{ $ann['number'] ?? $loop->iteration }}
                                                            </span>
                                                        @endforeach
                                                    </div>

                                                    <figcaption class="mt-2 text-center text-xs font-medium text-slate-500 dark:text-slate-400">
                                                        Gambar {{ $figureCounter }}: {{ $step->image_caption ?: $step->step_title }}
                                                    </figcaption>

                                                    @if (! empty($annotations))
                                                        <div class="mt-3 rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                                                            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                                                Keterangan Anotasi
                                                            </div>
                                                            <ul class="mt-2 grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                                                                @foreach ($annotations as $ann)
                                                                    <li class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300">
                                                                        <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#17324D] text-[10px] font-bold text-white">
                                                                            {{ $ann['number'] ?? $loop->iteration }}
                                                                        </span>
                                                                        <span>{{ $ann['label'] ?? '' }}</span>
                                                                    </li>
                                                                @endforeach
                                                            </ul>
                                                        </div>
                                                    @endif
                                                </figure>
                                            @endif

                                            @if ($step->action_instruction || $step->expected_result)
                                                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                                    @if ($step->action_instruction)
                                                        <div class="rounded-xl border border-brand-secondary/20 bg-white p-3.5 dark:border-slate-700 dark:bg-slate-900">
                                                            <div class="text-[11px] font-bold uppercase tracking-wider text-brand-secondary dark:text-blue-300">
                                                                Instruksi Tindakan
                                                            </div>
                                                            <p class="mt-1 text-xs leading-relaxed text-slate-700 dark:text-slate-300">
                                                                {{ $step->action_instruction }}
                                                            </p>
                                                        </div>
                                                    @endif

                                                    @if ($step->expected_result)
                                                        <div class="rounded-xl border border-emerald-600/20 bg-emerald-50/40 p-3.5 dark:border-emerald-500/20 dark:bg-emerald-950/20">
                                                            <div class="text-[11px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
                                                                Hasil yang Diharapkan
                                                            </div>
                                                            <p class="mt-1 text-xs leading-relaxed text-slate-700 dark:text-slate-300">
                                                                {{ $step->expected_result }}
                                                            </p>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif
                                        </section>
                                    @endforeach

                                    {{-- Lightbox Modal Perbesar Gambar --}}
                                    <div x-show="zoomImage"
                                        x-cloak
                                        @click.self="zoomImage = null"
                                        @keydown.escape.window="zoomImage = null"
                                        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4">
                                        <div class="max-h-[90vh] max-w-5xl overflow-auto rounded-2xl bg-white p-4 dark:bg-slate-900">
                                            <div class="mb-2 flex items-center justify-between">
                                                <span class="text-xs font-semibold text-slate-700 dark:text-slate-200" x-text="zoomCaption"></span>
                                                <button type="button" @click="zoomImage = null" class="rounded-lg px-2 py-1 text-xs font-bold text-slate-500 hover:bg-slate-100">Tutup</button>
                                            </div>
                                            <img :src="zoomImage" :alt="zoomCaption" class="max-h-[78vh] w-auto rounded-lg object-contain">
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if (! empty($article->attachments))
                                <div class="mt-8 border-t border-slate-200 pt-5 dark:border-slate-800">
                                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                        Lampiran Dokumen
                                    </h3>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach ($article->attachments as $path)
                                            <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($path) }}"
                                                target="_blank"
                                                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-brand-primary hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-blue-300">
                                                <x-heroicon-m-paper-clip class="h-4 w-4" />
                                                {{ basename($path) }}
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Feedback Membantu --}}
                            <div class="mt-8 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/50">
                                <div>
                                    <div class="text-xs font-semibold text-slate-800 dark:text-slate-200">
                                        Apakah panduan ini membantu Anda?
                                    </div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400">
                                        Masukan Anda membantu tim UPT ICT meningkatkan dokumentasi.
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button"
                                        wire:click="submitFeedback({{ $article->id }}, true)"
                                        class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold transition {{ $userFeedback?->is_helpful === true ? 'border-emerald-600 bg-emerald-50 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200' }}">
                                        <x-heroicon-m-hand-thumb-up class="h-4 w-4" />
                                        Ya, Membantu
                                    </button>
                                    <button type="button"
                                        wire:click="submitFeedback({{ $article->id }}, false)"
                                        class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold transition {{ $userFeedback?->is_helpful === false ? 'border-rose-600 bg-rose-50 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200' }}">
                                        <x-heroicon-m-hand-thumb-down class="h-4 w-4" />
                                        Perlu Perbaikan
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Navigasi Sebelumnya / Berikutnya --}}
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            @if ($previousArticle)
                                <button type="button"
                                    wire:click="openArticle('{{ $previousArticle->slug }}')"
                                    class="flex flex-col items-start rounded-xl border border-slate-200 bg-white p-4 text-left hover:border-brand-secondary dark:border-slate-800 dark:bg-slate-900">
                                    <span class="text-[11px] font-semibold uppercase text-slate-400">&larr; Sebelumnya</span>
                                    <span class="mt-1 text-xs font-bold text-slate-800 dark:text-slate-100">{{ $previousArticle->title }}</span>
                                </button>
                            @else
                                <div></div>
                            @endif

                            @if ($nextArticle)
                                <button type="button"
                                    wire:click="openArticle('{{ $nextArticle->slug }}')"
                                    class="flex flex-col items-end rounded-xl border border-slate-200 bg-white p-4 text-right hover:border-brand-secondary dark:border-slate-800 dark:bg-slate-900">
                                    <span class="text-[11px] font-semibold uppercase text-slate-400">Berikutnya &rarr;</span>
                                    <span class="mt-1 text-xs font-bold text-slate-800 dark:text-slate-100">{{ $nextArticle->title }}</span>
                                </button>
                            @endif
                        </div>
                    </article>

                    {{-- Sidebar Sticky Desktop: Daftar Isi & Artikel Terkait --}}
                    <aside class="min-w-0 space-y-5 lg:col-span-3">
                        <div class="space-y-5 lg:sticky lg:top-20">
                            @if (! empty($toc))
                                <div class="hidden rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900 lg:block">
                                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                        Daftar Isi
                                    </h3>
                                    <ul class="mt-3 space-y-2 text-xs">
                                        @foreach ($toc as $item)
                                            <li class="{{ $item['level'] === 3 ? 'pl-3' : '' }}">
                                                <a href="#{{ $item['id'] }}" class="block leading-snug text-slate-600 hover:text-brand-primary dark:text-slate-300 dark:hover:text-white">
                                                    {{ $item['title'] }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @if ($relatedArticles->isNotEmpty())
                                <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                        Panduan Terkait
                                    </h3>
                                    <div class="mt-3 space-y-2.5">
                                        @foreach ($relatedArticles as $related)
                                            <button type="button"
                                                wire:click="openArticle('{{ $related->slug }}')"
                                                class="block w-full rounded-xl border border-slate-100 bg-slate-50 p-3 text-left hover:border-brand-secondary dark:border-slate-800 dark:bg-slate-800/60">
                                                <div class="text-xs font-semibold text-slate-800 dark:text-slate-100">{{ $related->title }}</div>
                                                <div class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">{{ $related->reading_time }} menit baca</div>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </aside>
                </div>
            </div>
        @else
            {{-- HALAMAN UTAMA KNOWLEDGE CENTER --}}
            <div class="w-full min-w-0 space-y-6">
                {{-- Header, Tombol Unduh Buku Panduan PDF, & Search Bar --}}
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                        <div class="max-w-2xl">
                            <div class="inline-flex items-center gap-2 rounded-md bg-brand-highlight/15 px-2.5 py-1 text-xs font-semibold text-brand-primary dark:text-amber-300">
                                Peran Aktif: {{ $activeRoleLabel }}
                            </div>
                            <h2 class="mt-2.5 text-xl font-bold tracking-tight text-slate-900 dark:text-white">
                                Pusat Panduan Penggunaan Aplikasi
                            </h2>
                            <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                                Dokumentasi operasional resmi berdasarkan peran aktif Anda. Unduh buku panduan dalam format PDF untuk penggunaan luring.
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2.5">
                            <a href="{{ route('knowledge-center.handbook.role') }}"
                                target="_blank"
                                class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-4 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-brand-secondary">
                                <x-heroicon-m-document-arrow-down class="h-4 w-4" />
                                Unduh Buku Panduan ({{ $totalAccessibleCount }})
                            </a>

                            @if ($canDownloadComplete)
                                <a href="{{ route('knowledge-center.handbook.complete') }}"
                                    target="_blank"
                                    class="inline-flex items-center gap-2 rounded-xl border border-brand-accent/40 bg-brand-accent/10 px-4 py-2 text-xs font-semibold text-brand-accent transition hover:bg-brand-accent/20 dark:text-teal-300">
                                    <x-heroicon-m-book-open class="h-4 w-4" />
                                    Unduh Buku Panduan Lengkap
                                </a>
                            @endif
                        </div>
                    </div>

                    {{-- Search & Filter Modul --}}
                    <div class="mt-5 flex flex-col gap-3 border-t border-slate-100 pt-4 dark:border-slate-800 sm:flex-row">
                        <div class="flex-1">
                            <input type="text"
                                wire:model.live.debounce.300ms="search"
                                placeholder="Cari judul atau kata kunci panduan..."
                                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2 text-xs text-slate-800 placeholder-slate-400 focus:border-brand-secondary focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100" />
                        </div>

                        @if (! empty($moduleOptions))
                            <select wire:model.live="selectedModule"
                                class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 sm:w-56">
                                <option value="">Semua Modul</option>
                                @foreach ($moduleOptions as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>

                    {{-- Filter Kategori --}}
                    @if ($categories->isNotEmpty())
                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <button type="button"
                                wire:click="clearFilters"
                                class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition {{ $selectedCategory === '' && $search === '' && $selectedModule === '' ? 'border-brand-primary bg-brand-primary text-white' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300' }}">
                                Semua Panduan
                            </button>
                            @foreach ($categories as $category)
                                <button type="button"
                                    wire:click="selectCategory('{{ $category->slug }}')"
                                    class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition {{ $selectedCategory === $category->slug ? 'border-brand-primary bg-brand-primary text-white' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300' }}">
                                    {{ $category->name }}
                                    <span class="ml-1 opacity-75">({{ $category->accessible_articles_count }})</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Artikel Penting / Unggulan --}}
                @if ($featuredArticles->isNotEmpty())
                    <div class="space-y-3">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            Panduan Utama untuk Peran Anda
                        </h3>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            @foreach ($featuredArticles as $featured)
                                <button type="button"
                                    wire:click="openArticle('{{ $featured->slug }}')"
                                    class="group flex flex-col justify-between rounded-2xl border border-brand-primary/20 bg-white p-5 text-left shadow-xs transition hover:border-brand-secondary dark:border-slate-700 dark:bg-slate-900">
                                    <div>
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="rounded bg-brand-highlight/15 px-2 py-0.5 text-[11px] font-semibold text-brand-primary dark:text-amber-300">
                                                Panduan Utama
                                            </span>
                                            <span class="text-[11px] text-slate-400">{{ $featured->reading_time }} menit</span>
                                        </div>
                                        <h4 class="mt-2.5 text-sm font-bold text-slate-900 group-hover:text-brand-secondary dark:text-white">
                                            {{ $featured->title }}
                                        </h4>
                                        <p class="mt-1 line-clamp-2 text-xs text-slate-500 dark:text-slate-400">
                                            {{ $featured->excerpt }}
                                        </p>
                                    </div>
                                    <div class="mt-3 text-xs font-semibold text-brand-secondary">
                                        Baca Panduan &rarr;
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Daftar Seluruh Artikel yang Dapat Diakses --}}
                <div class="space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        Daftar Panduan ({{ $articles->count() }})
                    </h3>

                    @if ($articles->isEmpty())
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center dark:border-slate-700 dark:bg-slate-900">
                            <x-heroicon-o-document-magnifying-glass class="mx-auto h-10 w-10 text-slate-400" />
                            <p class="mt-3 text-sm font-semibold text-slate-700 dark:text-slate-200">
                                Panduan tidak ditemukan
                            </p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                Coba ubah kata kunci pencarian atau atur ulang filter kategori.
                            </p>
                        </div>
                    @else
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                            @foreach ($articles as $item)
                                <button type="button"
                                    wire:click="openArticle('{{ $item->slug }}')"
                                    class="flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-5 text-left shadow-xs transition hover:border-brand-secondary dark:border-slate-800 dark:bg-slate-900">
                                    <div>
                                        <div class="flex items-center justify-between gap-2 text-[11px]">
                                            <span class="rounded bg-slate-100 px-2 py-0.5 font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                {{ $item->category?->name }}
                                            </span>
                                            <span class="text-slate-400">{{ $item->reading_time }} menit</span>
                                        </div>
                                        <h4 class="mt-2.5 text-sm font-bold text-slate-900 dark:text-white">
                                            {{ $item->title }}
                                        </h4>
                                        <p class="mt-1.5 line-clamp-2 text-xs text-slate-500 dark:text-slate-400">
                                            {{ $item->excerpt }}
                                        </p>
                                    </div>

                                    <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3 text-[11px] text-slate-400 dark:border-slate-800">
                                        <span>
                                            @if ($item->roles->isEmpty())
                                                Semua Peran
                                            @else
                                                {{ $item->roles->pluck('name')->join(', ') }}
                                            @endif
                                        </span>
                                        <span>{{ $item->updated_at?->translatedFormat('d M Y') }}</span>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
