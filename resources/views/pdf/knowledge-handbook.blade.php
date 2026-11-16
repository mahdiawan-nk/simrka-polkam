<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $meta['doc_title'] }} - {{ $meta['brand_name'] }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 22mm 18mm 20mm 18mm;
        }

        @page :first {
            margin: 0;
        }

        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 9.5pt;
            line-height: 1.55;
            color: #1E293B;
            margin: 0;
            padding: 0;
        }

        /* Running Header & Footer (disembunyikan di cover :first) */
        .running-header {
            position: fixed;
            top: -14mm;
            left: 0;
            right: 0;
            border-bottom: 1px solid #DCE3EA;
            padding-bottom: 3px;
            font-size: 7.5pt;
            color: #64748B;
        }

        .running-footer {
            position: fixed;
            bottom: -12mm;
            left: 0;
            right: 0;
            border-top: 1px solid #DCE3EA;
            padding-top: 4px;
            font-size: 7.5pt;
            color: #64748B;
        }

        /* Cover Page */
        .cover-page {
            page-break-after: always;
            height: 297mm;
            width: 210mm;
            background-color: #17324D;
            color: #FFFFFF;
            position: relative;
        }

        .cover-accent-top {
            height: 6mm;
            background-color: #C69A45;
            width: 100%;
        }

        .cover-inner {
            padding: 28mm 22mm 22mm 22mm;
        }

        .cover-logo {
            max-height: 22mm;
            max-width: 65mm;
            margin-bottom: 8mm;
            background-color: #FFFFFF;
            padding: 3mm;
            border-radius: 3mm;
        }

        .cover-institution {
            font-size: 11pt;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #F3D79B;
            margin-bottom: 4mm;
        }

        .cover-brand {
            font-size: 16pt;
            font-weight: bold;
            color: #FFFFFF;
            margin-bottom: 2mm;
        }

        .cover-app-name {
            font-size: 10.5pt;
            color: #CBD5E1;
            margin-bottom: 18mm;
        }

        .cover-rule {
            width: 30mm;
            height: 1.2mm;
            background-color: #168C83;
            margin-bottom: 10mm;
        }

        .cover-title {
            font-size: 24pt;
            font-weight: bold;
            line-height: 1.2;
            color: #FFFFFF;
            margin-bottom: 5mm;
        }

        .cover-subtitle {
            font-size: 11.5pt;
            line-height: 1.45;
            color: #E2E8F0;
            margin-bottom: 16mm;
        }

        .cover-meta-box {
            border: 1px solid #245B85;
            background-color: #1E3E5F;
            padding: 5mm 6mm;
            border-radius: 2mm;
            font-size: 9pt;
        }

        .cover-meta-table {
            width: 100%;
            border-collapse: collapse;
        }

        .cover-meta-table td {
            padding: 1.5mm 0;
            color: #E2E8F0;
            border: none;
        }

        .cover-meta-label {
            width: 40%;
            color: #94A3B8;
            font-weight: bold;
        }

        .page-break {
            page-break-after: always;
        }

        .section-heading {
            font-size: 14pt;
            font-weight: bold;
            color: #17324D;
            border-bottom: 2px solid #17324D;
            padding-bottom: 2mm;
            margin-bottom: 5mm;
        }

        .identity-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8mm;
        }

        .identity-table td {
            border: 1px solid #DCE3EA;
            padding: 2.5mm 3.5mm;
            font-size: 9pt;
        }

        .identity-key {
            width: 34%;
            background-color: #F5F7FA;
            font-weight: bold;
            color: #17324D;
        }

        .preface-box {
            background-color: #F8FAFC;
            border-left: 3px solid #245B85;
            padding: 4mm 5mm;
            font-size: 9.2pt;
            color: #334155;
        }

        /* Daftar Isi */
        .toc-chapter {
            font-size: 10pt;
            font-weight: bold;
            color: #17324D;
            margin-top: 3.5mm;
            margin-bottom: 1.5mm;
        }

        .toc-item {
            font-size: 9pt;
            color: #334155;
            padding-left: 5mm;
            margin-bottom: 1mm;
        }

        /* Bab & Artikel */
        .chapter-banner {
            background-color: #17324D;
            color: #FFFFFF;
            padding: 3.5mm 4.5mm;
            border-radius: 1.5mm;
            margin-bottom: 5mm;
            page-break-inside: avoid;
        }

        .chapter-label {
            font-size: 7.5pt;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #F3D79B;
            font-weight: bold;
        }

        .chapter-title {
            font-size: 13pt;
            font-weight: bold;
            margin-top: 1mm;
        }

        .article-block {
            margin-bottom: 7mm;
            padding-bottom: 5mm;
            border-bottom: 1px solid #DCE3EA;
        }

        .article-title {
            font-size: 11.5pt;
            font-weight: bold;
            color: #17324D;
            margin-bottom: 1.5mm;
            page-break-after: avoid;
        }

        .article-meta {
            font-size: 7.8pt;
            color: #64748B;
            margin-bottom: 3mm;
            page-break-after: avoid;
        }

        .article-body h2 {
            font-size: 10pt;
            font-weight: bold;
            color: #245B85;
            margin-top: 3.5mm;
            margin-bottom: 1.5mm;
            page-break-after: avoid;
        }

        .article-body h3 {
            font-size: 9.5pt;
            font-weight: bold;
            color: #1E293B;
            margin-top: 2.5mm;
            margin-bottom: 1mm;
            page-break-after: avoid;
        }

        .article-body p {
            margin: 0 0 2.5mm 0;
        }

        .article-body ul,
        .article-body ol {
            margin: 0 0 3mm 0;
            padding-left: 5.5mm;
        }

        .article-body li {
            margin-bottom: 1.2mm;
        }

        .article-body table {
            width: 100%;
            border-collapse: collapse;
            margin: 3mm 0;
            page-break-inside: avoid;
        }

        .article-body th,
        .article-body td {
            border: 1px solid #DCE3EA;
            padding: 1.8mm 2.5mm;
            font-size: 8.5pt;
            text-align: left;
        }

        .article-body th {
            background-color: #F5F7FA;
            font-weight: bold;
        }

        .article-body img {
            max-width: 100%;
            height: auto;
            page-break-inside: avoid;
        }

        .closing-box {
            margin-top: 8mm;
            border: 1px solid #DCE3EA;
            background-color: #F8FAFC;
            padding: 5mm;
            border-radius: 1.5mm;
            page-break-inside: avoid;
        }

        /* Visual Workflow & Steps in PDF */
        .wf-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 2mm 0;
            margin: 3mm 0 5mm 0;
            page-break-inside: avoid;
        }

        .wf-cell {
            border: 1px solid #245B85;
            background-color: #F8FAFC;
            padding: 2.5mm;
            border-radius: 1.5mm;
            vertical-align: top;
            font-size: 8pt;
        }

        .step-card {
            border: 1px solid #DCE3EA;
            background-color: #F8FAFC;
            padding: 3.5mm 4mm;
            border-radius: 1.5mm;
            margin-bottom: 4.5mm;
            page-break-inside: avoid;
        }

        .step-title {
            font-size: 9.8pt;
            font-weight: bold;
            color: #17324D;
            margin-bottom: 1.5mm;
        }

        .step-figure {
            margin: 2.5mm 0;
            text-align: center;
            page-break-inside: avoid;
        }

        .step-img-wrap {
            position: relative;
            display: inline-block;
            border: 1px solid #DCE3EA;
            background-color: #FFFFFF;
            max-width: 100%;
        }

        .step-img {
            max-width: 100%;
            max-height: 105mm;
            width: auto;
            height: auto;
            display: block;
        }

        .step-caption {
            font-size: 7.8pt;
            color: #64748B;
            font-style: italic;
            margin-top: 1.5mm;
        }

        .step-info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2mm;
        }

        .step-info-grid td {
            width: 50%;
            vertical-align: top;
            border: 1px solid #DCE3EA;
            background-color: #FFFFFF;
            padding: 2mm 2.5mm;
            font-size: 8pt;
        }
    </style>
</head>
<body>

    {{-- 1. COVER PAGE --}}
    <div class="cover-page">
        <div class="cover-accent-top"></div>
        <div class="cover-inner">
            @if ($meta['logo_data_uri'])
                <img src="{{ $meta['logo_data_uri'] }}" alt="{{ $meta['brand_name'] }}" class="cover-logo">
            @endif

            <div class="cover-institution">{{ $meta['institution_name'] }}</div>
            <div class="cover-brand">{{ $meta['brand_name'] }} ({{ $meta['institution_short_name'] }})</div>
            <div class="cover-app-name">{{ $meta['app_full_name'] }}</div>

            <div class="cover-rule"></div>

            <div class="cover-title">{{ $meta['doc_title'] }}</div>
            <div class="cover-subtitle">{{ $meta['doc_subtitle'] }}</div>

            <div class="cover-meta-box">
                <table class="cover-meta-table">
                    <tr>
                        <td class="cover-meta-label">Cakupan Pengguna</td>
                        <td>: {{ $meta['scope_label'] }}</td>
                    </tr>
                    <tr>
                        <td class="cover-meta-label">Jumlah Panduan</td>
                        <td>: {{ $meta['article_count'] }} Artikel Terverifikasi</td>
                    </tr>
                    <tr>
                        <td class="cover-meta-label">Versi Dokumentasi</td>
                        <td>: v{{ $meta['doc_version'] }}</td>
                    </tr>
                    <tr>
                        <td class="cover-meta-label">Tanggal Penerbitan</td>
                        <td>: {{ $meta['published_date'] }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    {{-- Running Header & Footer untuk halaman isi --}}
    <div class="running-header">
        <span>{{ $meta['brand_name'] }} &mdash; {{ $meta['institution_name'] }}</span>
        <span style="float: right;">{{ $meta['doc_title'] }}</span>
    </div>

    <div class="running-footer">
        <span>Versi {{ $meta['doc_version'] }} &bull; Diterbitkan {{ $meta['published_date'] }}</span>
    </div>

    {{-- 2. HALAMAN IDENTITAS DOKUMEN & KATA PENGANTAR --}}
    <div class="page-break">
        <div class="section-heading">Identitas Dokumen</div>
        <table class="identity-table">
            <tr>
                <td class="identity-key">Nama Aplikasi</td>
                <td>{{ $meta['brand_name'] }} ({{ $meta['institution_short_name'] }}) &mdash; {{ $meta['app_full_name'] }}</td>
            </tr>
            <tr>
                <td class="identity-key">Institusi</td>
                <td>{{ $meta['institution_name'] }}</td>
            </tr>
            <tr>
                <td class="identity-key">Judul Dokumen</td>
                <td>{{ $meta['doc_title'] }}</td>
            </tr>
            <tr>
                <td class="identity-key">Cakupan Peran</td>
                <td>{{ $meta['scope_label'] }}</td>
            </tr>
            <tr>
                <td class="identity-key">Versi &amp; Tanggal Terbit</td>
                <td>Versi {{ $meta['doc_version'] }} &mdash; {{ $meta['published_date'] }}</td>
            </tr>
        </table>

        <div class="section-heading">Kata Pengantar</div>
        <div class="preface-box">
            <p style="margin-top: 0;">
                Buku panduan ini disusun secara resmi dari Pusat Panduan (<em>Knowledge Center</em>) aplikasi
                <strong>{{ $meta['brand_name'] }}</strong> di lingkungan <strong>{{ $meta['institution_name'] }}</strong>.
                Seluruh materi di dalam dokumen ini disesuaikan dengan hak akses serta alur kerja aktual aplikasi.
            </p>
            <p style="margin-bottom: 0;">
                Gunakan buku panduan ini sebagai acuan operasional sehari-hari dalam penyusunan Rencana Kerja dan Anggaran (RKA),
                pemantauan pagu, serta pengajuan dan verifikasi pencairan dana.
            </p>
        </div>
    </div>

    {{-- 3. DAFTAR ISI --}}
    <div class="page-break">
        <div class="section-heading">Daftar Isi</div>
        @if ($chapters->isEmpty())
            <p style="color: #64748B;">Belum ada artikel panduan yang diterbitkan untuk peran ini.</p>
        @else
            @foreach ($chapters as $chapterIndex => $chapter)
                @php $babNo = $chapterIndex + 1; @endphp
                <div class="toc-chapter">Bab {{ $babNo }}. {{ $chapter['category_name'] }}</div>
                @foreach ($chapter['articles'] as $articleIndex => $article)
                    <div class="toc-item">{{ $babNo }}.{{ $articleIndex + 1 }} {{ $article->title }}</div>
                @endforeach
            @endforeach
        @endif

        @if (! empty($figures ?? []))
            <div class="section-heading" style="margin-top: 7mm;">Daftar Gambar</div>
            @foreach ($figures as $fig)
                <div class="toc-item">
                    <strong>Gambar {{ $fig['number'] }}.</strong> {{ $fig['caption'] }}
                    <span style="color: #64748B;">({{ $fig['article_title'] }})</span>
                </div>
            @endforeach
        @endif
    </div>

    {{-- 4. ISI PANDUAN PER KATEGORI --}}
    @foreach ($chapters as $chapterIndex => $chapter)
        @php $babNo = $chapterIndex + 1; @endphp
        <div class="{{ $loop->last ? '' : 'page-break' }}">
            <div class="chapter-banner">
                <div class="chapter-label">Bab {{ $babNo }}</div>
                <div class="chapter-title">{{ $chapter['category_name'] }}</div>
            </div>

            @if ($chapter['category_description'])
                <p style="color: #475569; margin-bottom: 5mm;">{{ $chapter['category_description'] }}</p>
            @endif

            @foreach ($chapter['articles'] as $articleIndex => $article)
                <div class="article-block">
                    <div class="article-title">{{ $babNo }}.{{ $articleIndex + 1 }} {{ $article->title }}</div>
                    <div class="article-meta">
                        Target Peran:
                        {{ $article->roles->isEmpty() ? 'Semua Peran' : $article->roles->pluck('name')->join(', ') }}
                        &bull; Estimasi Baca: {{ $article->reading_time }} menit
                        &bull; Versi {{ $article->doc_version }}
                    </div>
                    <div class="article-body">
                        {!! $article->content !!}
                    </div>

                    @if (! empty($article->workflow_steps))
                        <div style="margin-top: 3mm; page-break-inside: avoid;">
                            <div style="font-size: 8.5pt; font-weight: bold; color: #17324D; margin-bottom: 1.5mm;">
                                Diagram Alur Kerja
                            </div>
                            <table class="wf-table">
                                <tr>
                                    @foreach ($article->workflow_steps as $wfIndex => $wf)
                                        <td class="wf-cell">
                                            <strong style="color: #17324D;">{{ $wfIndex + 1 }}. {{ $wf['title'] ?? '' }}</strong>
                                            @if (! empty($wf['actor']))
                                                <div style="color: #245B85; font-size: 7.5pt; margin-top: 0.8mm;">{{ $wf['actor'] }}</div>
                                            @endif
                                            @if (! empty($wf['note']))
                                                <div style="color: #64748B; font-size: 7.2pt; margin-top: 0.8mm;">{{ $wf['note'] }}</div>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            </table>
                        </div>
                    @endif

                    @if ($article->steps && $article->steps->isNotEmpty())
                        <div style="margin-top: 4mm;">
                            <div style="font-size: 9pt; font-weight: bold; color: #17324D; margin-bottom: 2.5mm;">
                                Panduan Visual Langkah demi Langkah
                            </div>
                            @foreach ($article->steps as $stepIndex => $step)
                                <div class="step-card">
                                    <div class="step-title">Langkah {{ $stepIndex + 1 }}: {{ $step->step_title }}</div>
                                    @if ($step->step_description)
                                        <p style="margin: 0 0 2mm 0; font-size: 8.5pt; color: #334155;">{{ $step->step_description }}</p>
                                    @endif

                                    @php $imgMeta = ($stepImages ?? [])[$step->id] ?? null; @endphp
                                    @if ($imgMeta)
                                        <div class="step-figure">
                                            <div class="step-img-wrap">
                                                <img src="{{ $imgMeta['data_uri'] }}" alt="{{ $step->image_alt ?: $step->step_title }}" class="step-img">
                                            </div>
                                            <div class="step-caption">
                                                Gambar {{ $imgMeta['figure_number'] }}: {{ $step->image_caption ?: $step->step_title }}
                                            </div>
                                        </div>
                                    @endif

                                    @if (! empty($step->annotations))
                                        <div style="margin-top: 1.5mm; font-size: 7.8pt; color: #334155;">
                                            <strong>Keterangan Anotasi:</strong>
                                            @foreach ($step->annotations as $ann)
                                                <span style="margin-right: 3mm;">
                                                    [<strong>{{ $ann['number'] ?? $loop->iteration }}</strong>] {{ $ann['label'] ?? '' }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if ($step->action_instruction || $step->expected_result)
                                        <table class="step-info-grid">
                                            <tr>
                                                @if ($step->action_instruction)
                                                    <td>
                                                        <strong style="color: #245B85;">Instruksi Tindakan:</strong><br>
                                                        {{ $step->action_instruction }}
                                                    </td>
                                                @endif
                                                @if ($step->expected_result)
                                                    <td>
                                                        <strong style="color: #15803D;">Hasil yang Diharapkan:</strong><br>
                                                        {{ $step->expected_result }}
                                                    </td>
                                                @endif
                                            </tr>
                                        </table>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endforeach

    {{-- 5. PENUTUP --}}
    <div class="closing-box">
        <strong style="color: #17324D;">Penutup &amp; Dukungan Teknis</strong>
        <p style="margin: 2mm 0 0 0; font-size: 8.8pt; color: #475569;">
            Dokumen ini diterbitkan secara dinamis oleh {{ $meta['brand_name'] }} ({{ $meta['institution_name'] }}) pada {{ $meta['published_date'] }}.
            Apabila terdapat kendala teknis atau pembaruan prosedur, silakan hubungi tim UPT ICT atau pengelola sistem institusi.
        </p>
    </div>

    {{-- Penomoran Halaman Otomatis DomPDF (kecuali halaman 1 / Cover) --}}
    <script type="text/php">
        if (isset($pdf)) {
            $pdf->page_script('
                if ($PAGE_NUM > 1) {
                    $font = $fontMetrics->get_font("DejaVu Sans", "normal");
                    $text = "Halaman " . ($PAGE_NUM - 1) . " dari " . ($PAGE_COUNT - 1);
                    $pdf->text(470, 812, $text, $font, 7.5, [0.39, 0.45, 0.55]);
                }
            ');
        }
    </script>
</body>
</html>
