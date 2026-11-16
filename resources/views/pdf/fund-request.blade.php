<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Formulir Permohonan Pencairan Dana - {{ $record->document_number }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 14mm 14mm 14mm;
        }

        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 8.5pt;
            line-height: 1.38;
            color: #1f2937;
            background-color: #ffffff;
            margin: 0;
            padding: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #ffffff;
        }

        thead {
            display: table-header-group;
        }

        tr {
            page-break-inside: avoid;
        }

        .kop-wrapper {
            margin-bottom: 8px;
        }

        .kop-full-img {
            width: 100%;
            height: auto;
            display: block;
        }

        .kop-manual-table td {
            vertical-align: middle;
            border: none;
            padding: 2px 4px;
            background-color: #ffffff;
        }

        .kop-logo {
            max-height: 62px;
            max-width: 76px;
            width: auto;
            height: auto;
        }

        .kop-foundation {
            font-size: 10pt;
            font-weight: bold;
            letter-spacing: 0.4px;
            color: #111827;
        }

        .kop-institution {
            font-size: 14.5pt;
            font-weight: bold;
            letter-spacing: 0.6px;
            color: #111827;
            margin: 1px 0;
        }

        .kop-address,
        .kop-contact {
            font-size: 7.5pt;
            line-height: 1.25;
            color: #374151;
        }

        .kop-divider {
            border-top: 2px solid #111827;
            border-bottom: 0.75px solid #111827;
            height: 2px;
            margin-top: 4px;
            margin-bottom: 8px;
        }

        .doc-title-block {
            text-align: center;
            margin-top: 6px;
            margin-bottom: 10px;
        }

        .doc-title {
            font-size: 11.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #111827;
        }

        .doc-subtitle {
            font-size: 9pt;
            color: #374151;
            margin-top: 2px;
        }

        .doc-status-note {
            font-size: 7.5pt;
            color: #4b5563;
            font-style: italic;
            margin-top: 2px;
        }

        .meta-table {
            width: 100%;
            margin-bottom: 8px;
        }

        .meta-table td {
            border: none;
            padding: 1.5px 0;
            font-size: 8.5pt;
            vertical-align: top;
            background-color: #ffffff;
        }

        .meta-label {
            width: 14%;
            color: #374151;
        }

        .meta-colon {
            width: 2%;
            text-align: center;
        }

        .meta-value {
            width: 84%;
            font-weight: bold;
            color: #111827;
        }

        .intro-text {
            margin: 6px 0 10px 0;
            font-size: 8.5pt;
            color: #1f2937;
            text-align: left;
        }

        .section-block {
            margin-bottom: 11px;
        }

        .section-keep {
            page-break-inside: avoid;
        }

        .section-heading {
            text-align: left;
            font-weight: bold;
            font-size: 8.8pt;
            color: #111827;
            border-bottom: 1px solid #374151;
            padding-bottom: 2.5px;
            margin-bottom: 5px;
        }

        .kv-table {
            width: 100%;
            margin-bottom: 2px;
        }

        .kv-table td {
            border: none;
            padding: 2px 0;
            vertical-align: top;
            font-size: 8.5pt;
            background-color: #ffffff;
        }

        .kv-label {
            width: 30%;
            color: #374151;
        }

        .kv-colon {
            width: 2.5%;
            text-align: center;
            color: #374151;
        }

        .kv-value {
            width: 67.5%;
            color: #111827;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
            background-color: #ffffff;
        }

        .data-table th,
        .data-table td {
            border: 0.7px solid #9ca3af;
            padding: 4px 5px;
            vertical-align: top;
            background-color: #ffffff;
            color: #1f2937;
        }

        .data-table th {
            font-weight: bold;
            text-align: center;
            font-size: 8pt;
            color: #111827;
            border-bottom: 1.1px solid #4b5563;
            vertical-align: middle;
        }

        .data-table td {
            font-size: 8pt;
        }

        .data-table .total-row td {
            font-weight: bold;
            font-size: 8.3pt;
            color: #111827;
            border-top: 1.1px solid #4b5563;
            background-color: #ffffff;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .text-red { color: #b91c1c; }
        .text-muted { color: #4b5563; }

        .item-subnote {
            display: block;
            font-size: 7.2pt;
            color: #4b5563;
            font-style: italic;
            margin-top: 1.5px;
        }

        .terbilang-line {
            margin-top: 4px;
            margin-bottom: 2px;
            font-size: 8.3pt;
            font-style: italic;
            color: #1f2937;
        }

        .footnote {
            margin-top: 3px;
            margin-bottom: 2px;
            font-size: 7.4pt;
            font-style: italic;
            color: #4b5563;
        }

        .checklist-table {
            width: 100%;
            border: none;
            margin-bottom: 2px;
        }

        .checklist-table td {
            border: none;
            padding: 2px 8px 2px 0;
            width: 50%;
            vertical-align: top;
            font-size: 8.3pt;
            background-color: #ffffff;
            color: #1f2937;
        }

        .check-item {
            margin-bottom: 3px;
        }

        .attachment-list {
            padding-top: 4px;
            font-size: 7.8pt;
            color: #374151;
        }

        .signature-block {
            margin-top: 16px;
            page-break-inside: avoid;
        }

        .sig-table {
            width: 100%;
            border: none;
        }

        .sig-table td {
            border: none;
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            padding: 2px 6px;
            font-size: 8.4pt;
            background-color: #ffffff;
            color: #111827;
        }

        .sig-space {
            height: 54px;
        }

        .director-block {
            margin-top: 14px;
            text-align: center;
            font-size: 8.4pt;
            color: #111827;
            page-break-inside: avoid;
        }

        .director-space {
            height: 52px;
        }
    </style>
</head>
<body>
    @php
        $pdf ??= app(\App\Services\FundRequest\FundRequestDetailService::class)->buildForPdf($record)['pdf'];
        $header = $pdf['header'];
        $signatures = $pdf['signatures'];
        $items = $record->items;
    @endphp

    {{-- KOP SURAT (DARI RKA SETTINGS: KONFIGURASI FORM PERMOHONAN PENCAIRAN DANA) --}}
    <div class="kop-wrapper">
        @if ($header['type'] === 'full_image' && $header['full_kop_data_uri'])
            <img src="{{ $header['full_kop_data_uri'] }}" alt="Kop Surat" class="kop-full-img">
        @else
            <table class="kop-manual-table">
                <tr>
                    @if ($header['type'] === 'manual_two_logos' || ($header['type'] === 'manual_single_logo' && $header['single_logo_position'] === 'left'))
                        <td style="width: 15%; text-align: left;">
                            @if ($header['left_logo_data_uri'])
                                <img src="{{ $header['left_logo_data_uri'] }}" alt="Logo Kiri" class="kop-logo">
                            @endif
                        </td>
                    @endif

                    <td style="text-align: center;">
                        @if ($header['foundation_name'])
                            <div class="kop-foundation">{{ $header['foundation_name'] }}</div>
                        @endif
                        <div class="kop-institution">{{ $header['institution_name'] }}</div>
                        @if ($header['address_line'])
                            <div class="kop-address">{{ $header['address_line'] }}</div>
                        @endif
                        @if ($header['contact_line'])
                            <div class="kop-contact">{{ $header['contact_line'] }}</div>
                        @endif
                    </td>

                    @if ($header['type'] === 'manual_two_logos')
                        <td style="width: 15%; text-align: right;">
                            @if ($header['right_logo_data_uri'])
                                <img src="{{ $header['right_logo_data_uri'] }}" alt="Logo Kanan" class="kop-logo">
                            @endif
                        </td>
                    @elseif ($header['type'] === 'manual_single_logo' && $header['single_logo_position'] === 'right')
                        <td style="width: 15%; text-align: right;">
                            @if ($header['left_logo_data_uri'])
                                <img src="{{ $header['left_logo_data_uri'] }}" alt="Logo Kanan" class="kop-logo">
                            @endif
                        </td>
                    @endif
                </tr>
            </table>
            @if ($header['show_divider_line'])
                <div class="kop-divider"></div>
            @endif
        @endif
    </div>

    {{-- JUDUL DOKUMEN --}}
    <div class="doc-title-block">
        <div class="doc-title">FORMULIR PERMOHONAN PENCAIRAN DANA</div>
        <div class="doc-subtitle">Tahun Anggaran {{ $record->fiscal_year }}</div>
        @if (strtolower((string) $record->status) !== 'approved')
            <div class="doc-status-note">
                Status: {{ $detail['status_label'] }} — Belum Disahkan (dokumen ini belum memperoleh pengesahan akhir Direktur)
            </div>
        @else
            <div class="doc-status-note">
                Status: {{ $detail['status_label'] }}
            </div>
        @endif
    </div>

    {{-- NOMOR & TANGGAL --}}
    <table class="meta-table">
        <tr>
            <td class="meta-label">Nomor</td>
            <td class="meta-colon">:</td>
            <td class="meta-value">{{ $record->document_number }}</td>
        </tr>
        <tr>
            <td class="meta-label">Tanggal</td>
            <td class="meta-colon">:</td>
            <td class="meta-value">{{ $pdf['request_date_formatted'] }}</td>
        </tr>
    </table>

    {{-- PENGANTAR --}}
    <div class="intro-text">
        Kepada Yth. Direktur Politeknik Kampar, melalui Wakil Direktur II Bidang Keuangan, Kepegawaian dan Umum.
        Dengan ini kami mengajukan permohonan pencairan dana sebagai berikut:
    </div>

    {{-- SECTION A. KEGIATAN --}}
    <div class="section-block section-keep">
        <div class="section-heading">A. KEGIATAN</div>
        <table class="kv-table">
            <tr>
                <td class="kv-label">Unit Kerja</td>
                <td class="kv-colon">:</td>
                <td class="kv-value">{{ $record->department?->name ?? '-' }}</td>
            </tr>
            <tr>
                <td class="kv-label">Nama Kegiatan / Keperluan</td>
                <td class="kv-colon">:</td>
                <td class="kv-value">{{ $record->activity_name }}</td>
            </tr>
            <tr>
                <td class="kv-label">Waktu Pelaksanaan</td>
                <td class="kv-colon">:</td>
                <td class="kv-value">{{ $pdf['execution_date_formatted'] }}</td>
            </tr>
            <tr>
                <td class="kv-label">Penanggung Jawab</td>
                <td class="kv-colon">:</td>
                <td class="kv-value">{{ $record->responsible_person }}</td>
            </tr>
            <tr>
                <td class="kv-label">Jenis Pencairan</td>
                <td class="kv-colon">:</td>
                <td class="kv-value">{{ $record->disbursement_type }}</td>
            </tr>
        </table>
    </div>

    {{-- SECTION B. RINCIAN KEBUTUHAN --}}
    <div class="section-block">
        <div class="section-heading">B. RINCIAN KEBUTUHAN (pilih kode akun di setiap baris)</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 4%;">No</th>
                    <th style="width: 10%;">Kode Akun</th>
                    <th style="width: 18%;">Nama Akun (otomatis)</th>
                    <th style="width: 29%;">Uraian</th>
                    <th style="width: 7%;">Volume</th>
                    <th style="width: 8%;">Satuan</th>
                    <th style="width: 11%;">Harga Satuan</th>
                    <th style="width: 13%;">Jumlah (Rp)</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $index => $item)
                    @php
                        $vStatus = $item->verification_status;
                    @endphp
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td class="text-center">{{ $item->account?->code ?? $item->rka_account_code_snapshot ?? '-' }}</td>
                        <td class="text-left">{{ $item->account?->name ?? $item->rka_account_name_snapshot ?? '-' }}</td>
                        <td class="text-left">
                            {{ $item->description }}
                            @if ($vStatus === 'rejected')
                                <span class="item-subnote">(Ditolak Keuangan)</span>
                            @elseif ($vStatus === 'partial')
                                <span class="item-subnote">(Disetujui sebagian: Rp {{ number_format((float) ($item->approved_price ?? 0), 0, ',', '.') }})</span>
                            @endif
                        </td>
                        <td class="text-center">
                            {{ rtrim(rtrim(number_format((float) $item->volume, 2, ',', '.'), '0'), ',') ?: '0' }}
                        </td>
                        <td class="text-center">{{ $item->unit }}</td>
                        <td class="text-right">{{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) $item->total_price, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted">Belum ada rincian kebutuhan.</td>
                    </tr>
                @endforelse
                <tr class="total-row">
                    <td colspan="7" class="text-right">JUMLAH YANG DIMOHONKAN</td>
                    <td class="text-right" style="white-space: nowrap;">Rp {{ number_format($detail['summary']['total_requested'], 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
        <div class="terbilang-line">
            <strong>Terbilang:</strong> &nbsp;({{ $pdf['terbilang'] }})
        </div>
    </div>

    {{-- SECTION C. REKAP PER KODE AKUN --}}
    <div class="section-block {{ count($pdf['section_c_rows']) <= 10 ? 'section-keep' : '' }}">
        <div class="section-heading">C. REKAP PER KODE AKUN (otomatis)</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 5%;">No</th>
                    <th style="width: 11%;">Kode</th>
                    <th style="width: 26%;">Nama Akun</th>
                    <th style="width: 20%;">Catatan</th>
                    <th style="width: 14%;">Jumlah Dimohonkan</th>
                    <th style="width: 13%;">Pagu Unit</th>
                    <th style="width: 11%;">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pdf['section_c_rows'] as $idx => $row)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td class="text-center">{{ $row['code'] }}</td>
                        <td class="text-left">{{ $row['name'] }}</td>
                        <td class="text-left {{ $row['is_centralized'] ? 'text-red' : '' }}">{{ $row['catatan'] }}</td>
                        <td class="text-right">{{ number_format($row['total_requested'], 0, ',', '.') }}</td>
                        <td class="text-right">{{ number_format($row['pagu_unit'], 0, ',', '.') }}</td>
                        <td class="text-center">{{ $row['status'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted">Belum ada rincian kode akun.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <p class="footnote">
            Catatan merah berarti pos dikelola terpusat; ajukan ke unit pengelolanya. Pagu adalah pagu setahun (bukan sisa); sisa pagu diperiksa bendahara.
        </p>
    </div>

    {{-- SECTION D. PEMBAYARAN KEPADA --}}
    <div class="section-block section-keep">
        <div class="section-heading">D. PEMBAYARAN KEPADA</div>
        <table class="kv-table">
            <tr>
                <td class="kv-label">Nama Penerima</td>
                <td class="kv-colon">:</td>
                <td class="kv-value">{{ $record->recipient_name ?: '-' }}</td>
            </tr>
            <tr>
                <td class="kv-label">Bank / No. Rekening (kosongkan jika tunai)</td>
                <td class="kv-colon">:</td>
                <td class="kv-value">{{ filled($record->bank_account_info) ? $record->bank_account_info : '' }}</td>
            </tr>
        </table>
    </div>

    {{-- SECTION E. LAMPIRAN --}}
    <div class="section-block section-keep">
        <div class="section-heading">E. LAMPIRAN (beri tanda &#10003;)</div>
        <table class="checklist-table">
            <tr>
                <td>
                    <div class="check-item">&#9744; &nbsp; TOR / KAK kegiatan</div>
                    <div class="check-item">&#9744; &nbsp; RAB rinci</div>
                    <div class="check-item">&#9744; &nbsp; SK panitia / tim (untuk honor)</div>
                </td>
                <td>
                    <div class="check-item">&#9744; &nbsp; Penawaran harga / nota / kuitansi</div>
                    <div class="check-item">&#9744; &nbsp; Daftar hadir / undangan / surat tugas</div>
                    <div class="check-item">&#9744; &nbsp; Foto / dokumentasi (untuk penggantian)</div>
                </td>
            </tr>
        </table>
        @if (! empty($detail['attachments']))
            <div class="attachment-list">
                <strong>Berkas terunggah ({{ count($detail['attachments']) }}):</strong>
                {{ collect($detail['attachments'])->pluck('name')->implode(', ') }}
            </div>
        @endif
    </div>

    {{-- SECTION F. VERIFIKASI DAN PERSETUJUAN PER KODE --}}
    <div class="section-block">
        <div class="section-heading">F. VERIFIKASI DAN PERSETUJUAN PER KODE (diisi Bagian Keuangan)</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 5%;">No</th>
                    <th style="width: 11%;">Kode</th>
                    <th style="width: 16%;">Kode Final (jika dikoreksi)</th>
                    <th style="width: 19%;">Sisa Pagu Sebelum (Rp)</th>
                    <th style="width: 17%;">Jumlah Disetujui (Rp)</th>
                    <th style="width: 16%;">Persetujuan</th>
                    <th style="width: 16%;">No. Bukti Register</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pdf['section_f_rows'] as $idx => $fRow)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td class="text-center">{{ $fRow['code'] }}</td>
                        <td class="text-center">{{ $fRow['final_code'] }}</td>
                        <td class="text-right">
                            {{ $fRow['remaining_budget'] !== null ? number_format($fRow['remaining_budget'], 0, ',', '.') : '' }}
                        </td>
                        <td class="text-right">
                            {{ $fRow['amount_approved'] !== null ? number_format($fRow['amount_approved'], 0, ',', '.') : '' }}
                        </td>
                        <td class="text-center">{{ $fRow['status'] }}</td>
                        <td class="text-center">{{ $fRow['register_proof_number'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted">Belum ada data verifikasi.</td>
                    </tr>
                @endforelse
                <tr class="total-row">
                    <td colspan="4" class="text-right">JUMLAH DISETUJUI</td>
                    <td class="text-right">Rp {{ number_format($pdf['section_f_total'], 0, ',', '.') }}</td>
                    <td colspan="2"></td>
                </tr>
            </tbody>
        </table>
        <p class="footnote">
            Pilih &#10004; Disetujui (jumlah otomatis sama dengan yang dimohonkan), &#10008; Ditolak (jumlah 0), atau &#9680; Disetujui sebagian lalu ketik jumlah yang disetujui. Hanya kode yang disetujui yang dicatat di Register.
        </p>
    </div>

    {{-- BLOK TANDA TANGAN --}}
    <div class="signature-block">
        <table class="sig-table">
            <tr>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>{{ $signatures['signing_place'] }}, {{ $signatures['signing_date'] }}</td>
            </tr>
            <tr>
                <td>
                    <div>{{ $signatures['pemohon']['stage_label'] }}</div>
                    <div><strong>{{ $signatures['pemohon']['role_title'] }}</strong></div>
                </td>
                <td>
                    <div>{{ $signatures['kabakku']['stage_label'] }}</div>
                    <div><strong>{{ $signatures['kabakku']['role_title'] }}</strong></div>
                </td>
                <td>
                    <div>{{ $signatures['wadir']['stage_label'] }}</div>
                    <div><strong>{{ $signatures['wadir']['role_title'] }}</strong></div>
                </td>
            </tr>
            <tr>
                <td class="sig-space">&nbsp;</td>
                <td class="sig-space">&nbsp;</td>
                <td class="sig-space">&nbsp;</td>
            </tr>
            <tr>
                <td>({{ $signatures['pemohon']['name'] }})</td>
                <td>({{ $signatures['kabakku']['name'] }})</td>
                <td>({{ $signatures['wadir']['name'] }})</td>
            </tr>
        </table>

        <div class="director-block">
            <div>{{ $signatures['direktur']['stage_label'] }}</div>
            <div><strong>{{ $signatures['direktur']['role_title'] }}</strong></div>
            <div class="director-space">&nbsp;</div>
            <div><strong>{{ $signatures['direktur']['name'] }}</strong></div>
            @if ($signatures['direktur']['nip'] !== '')
                <div>NRP. {{ $signatures['direktur']['nip'] }}</div>
            @endif
        </div>
    </div>
</body>
</html>
