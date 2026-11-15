<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Formulir Permohonan Pencairan Dana - {{ $record->document_number }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 15mm;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8.5pt;
            color: #000;
            line-height: 1.2;
        }

        header {
            position: fixed;
            top: -32mm;
            /* Tarik header lebih tinggi ke atas */
            left: 0;
            right: 0;
            height: 30mm;
            text-align: center;
        }

        /* Kop Surat Gambar Tunggal */
        .kop-container {
            width: 100%;
            text-align: center;
            margin-bottom: 5px;
        }

        .kop-img {
            width: 100%;
            height: auto;
            max-height: 85px;
            /* Menjaga agar tidak terlalu besar di kertas A4 */
            object-fit: contain;
        }

        /* Title */
        .title-box {
            text-align: center;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            padding: 4px 0;
            margin-top: 5px;
            margin-bottom: 10px;
        }

        .title-box h4 {
            margin: 0;
            font-size: 10pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        /* Meta Document */
        .meta-table {
            width: 100%;
            margin-bottom: 8px;
        }

        .meta-table td {
            padding: 1px 0;
        }

        /* Section Header */
        .section-header {
            background-color: #c5d9f1;
            font-weight: bold;
            padding: 2px 5px;
            border: 1px solid #000;
            margin-top: 6px;
            text-transform: uppercase;
            font-size: 8.5pt;
        }

        /* Standard Table Styling */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: -1px;
        }

        table.data-table th,
        table.data-table td {
            border: 1px solid #000;
            padding: 3px 4px;
            vertical-align: middle;
        }

        table.data-table th {
            background-color: #f2f2f2;
            text-align: center;
            font-weight: bold;
            font-size: 8pt;
        }

        /* Helpers */
        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .font-bold {
            font-weight: bold;
        }

        .bg-light {
            background-color: #f9f9f9;
        }

        /* Signatures */
        .sig-table {
            width: 100%;
            margin-top: 15px;
            border-collapse: collapse;
        }

        .sig-table td {
            text-align: center;
            vertical-align: top;
            width: 33.33%;
        }

        .sig-space {
            height: 45px;
        }
    </style>
</head>

<body>

    <!-- Kop Surat (Menggunakan Gambar Tunggal Kop Surat yang Sejajar) -->
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 2px;">
        <tr>
            <td style="padding: 0; text-align: center;">
                <img src="{{ public_path('images/kop-surat.png') }}" style="width: 100%; height: auto; display: block;"
                    alt="Kop Surat">
            </td>
        </tr>
    </table>

    <!-- Title -->
    <div class="title-box">
        <h4>FORMULIR PERMOHONAN PENCAIRAN DANA</h4>
        <div style="font-size: 8pt;">Tahun Anggaran {{ $record->fiscal_year }}</div>
    </div>

    <!-- Meta Info -->
    <table class="meta-table">
        <tr>
            <td style="width: 70px;">Nomor</td>
            <td style="width: 10px;">:</td>
            <td><strong>{{ $record->document_number }}</strong></td>
        </tr>
        <tr>
            <td>Tanggal</td>
            <td>:</td>
            <td>{{ \Carbon\Carbon::parse($record->request_date)->translatedFormat('d F Y') }}</td>
        </tr>
    </table>

    <p style="margin: 4px 0;">Kepada Yth. Direktur Politeknik Kampar, melalui Wakil Direktur II Bidang Keuangan,
        Kepegawaian dan Umum. Dengan ini kami mengajukan permohonan pencairan dana sebagai berikut:</p>

    <!-- SECTION A -->
    <div class="section-header">A. KEGIATAN</div>
    <table class="data-table">
        <tr>
            <td style="width: 25%;" class="bg-light">Unit Kerja</td>
            <td style="width: 75%;">{{ $record->department->name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="bg-light">Nama Kegiatan / Keperluan</td>
            <td>{{ $record->activity_name }}</td>
        </tr>
        <tr>
            <td class="bg-light">Waktu Pelaksanaan</td>
            <td>{{ \Carbon\Carbon::parse($record->execution_date)->translatedFormat('d F Y') }}</td>
        </tr>
        <tr>
            <td class="bg-light">Penanggung Jawab</td>
            <td>{{ $record->responsible_person }}</td>
        </tr>
        <tr>
            <td class="bg-light">Jenis Pencairan</td>
            <td>{{ $record->disbursement_type }}</td>
        </tr>
    </table>

    <!-- SECTION B -->
    <div class="section-header">B. RINCIAN KEBUTUHAN</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 25px;">No</th>
                <th style="width: 60px;">Kode Akun</th>
                <th style="width: 130px;">Nama Akun</th>
                <th>Uraian</th>
                <th style="width: 35px;">Vol</th>
                <th style="width: 40px;">Satuan</th>
                <th style="width: 80px;">Harga Satuan</th>
                <th style="width: 90px;">Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($record->items as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="text-center">{{ $item->account->code ?? '-' }}</td>
                    <td>{{ $item->account->name ?? '-' }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="text-center">{{ number_format($item->volume, 0, ',', '.') }}</td>
                    <td class="text-center">{{ $item->unit }}</td>
                    <td class="text-right">{{ number_format($item->unit_price, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($item->total_price, 0, ',', '.') }}</td>
                </tr>
            @endforeach
            @for ($i = count($record->items); $i < 5; $i++)
                <tr>
                    <td class="text-center">&nbsp;</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            @endfor
        </tbody>
        <tfoot>
            <tr class="font-bold bg-light">
                <td colspan="7" class="text-right">JUMLAH YANG DIMOHONKAN</td>
                <td class="text-right">Rp {{ number_format($record->total_requested, 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>

    <div style="margin: 4px 0; font-style: italic;">
        <strong>Terbilang:</strong> ({{ \Illuminate\Support\Str::title(terbilang($record->total_requested)) }}
        Rupiah)
    </div>

    <!-- SECTION C -->
    <div class="section-header">C. REKAP PER KODE AKUN (Otomatis)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 25px;">No</th>
                <th style="width: 70px;">Kode</th>
                <th>Nama Akun</th>
                <th style="width: 90px;">Jumlah Dimohonkan</th>
                <th style="width: 90px;">Pagu Unit</th>
                <th style="width: 70px;">Status</th>
            </tr>
        </thead>
        <tbody>
            @php
                $grouped = $record->items->groupBy('account_id');
            @endphp
            @foreach ($grouped as $accountId => $groupItems)
                @php
                    $acc = $groupItems->first()->account;
                    $sumReq = $groupItems->sum('total_price');
                    $pagu = $acc?->departmentBudgets->first()->allocated_amount ?? 0;
                @endphp
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td class="text-center">{{ $acc->code ?? '-' }}</td>
                    <td>{{ $acc->name ?? '-' }}</td>
                    <td class="text-right">{{ number_format($sumReq, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($pagu, 0, ',', '.') }}</td>
                    <td class="text-center">{{ $sumReq <= $pagu ? 'Sesuai pagu' : 'Melampaui' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- SECTION D -->
    <div class="section-header">D. PEMBAYARAN KEPADA</div>
    <table class="data-table">
        <tr>
            <td style="width: 25%;" class="bg-light">Nama Penerima</td>
            <td style="width: 75%;">{{ $record->recipient_name }}</td>
        </tr>
        <tr>
            <td class="bg-light">Bank / No. Rekening</td>
            <td>{{ $record->bank_account_info ?: '(Kosongkan jika tunai)' }}</td>
        </tr>
    </table>

    <!-- SECTION E -->
    <div class="section-header">E. LAMPIRAN</div>
    <table class="data-table">
        <tr>
            <td style="width: 50%;">
                [{{ in_array('TOR/KAK', $record->attachments ?? []) ? 'v' : ' ' }}] TOR / KAK Kegiatan<br>
                [{{ in_array('RAB', $record->attachments ?? []) ? 'v' : ' ' }}] RAB rinci<br>
                [{{ in_array('SK', $record->attachments ?? []) ? 'v' : ' ' }}] SK panitia / tim (untuk honor)
            </td>
            <td style="width: 50%;">
                [{{ in_array('Penawaran', $record->attachments ?? []) ? 'v' : ' ' }}] Penawaran harga / nota /
                kuitansi<br>
                [{{ in_array('DaftarHadir', $record->attachments ?? []) ? 'v' : ' ' }}] Daftar hadir / undangan /
                surat tugas<br>
                [{{ in_array('Foto', $record->attachments ?? []) ? 'v' : ' ' }}] Foto / dokumentasi (untuk
                penggantian)
            </td>
        </tr>
    </table>

    <!-- SECTION F -->
    <div class="section-header">F. VERIFIKASI DAN PERSETUJUAN PER KODE (Diisi Bagian Keuangan)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 25px;">No</th>
                <th style="width: 60px;">Kode</th>
                <th>Kode Final (Jika dikoreksi)</th>
                <th>Sisa Pagu Sebelum (Rp)</th>
                <th style="width: 90px;">Jumlah Disetujui (Rp)</th>
                <th style="width: 70px;">Persetujuan</th>
                <th style="width: 80px;">No. Bukti Register</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($record->verifications as $v)
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td class="text-center">{{ $v->account->code ?? '-' }}</td>
                    <td class="text-center">{{ $v->finalAccount->code ?? '-' }}</td>
                    <td class="text-right">{{ number_format($v->remaining_budget, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($v->amount_approved, 0, ',', '.') }}</td>
                    <td class="text-center">{{ $v->status }}</td>
                    <td class="text-center">{{ $v->register_proof_number ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="font-bold bg-light">
                <td colspan="4" class="text-right">JUMLAH DISETUJUI</td>
                <td class="text-right">Rp {{ number_format($record->total_approved, 0, ',', '.') }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>

    <!-- Tanda Tangan -->
    <table class="sig-table">
        <tr>
            <td colspan="3" style="text-align: right; padding-bottom: 10px;">
                Bangkinang, {{ \Carbon\Carbon::parse($record->updated_at)->translatedFormat('d F Y') }}
            </td>
        </tr>
        <tr>
            <td>
                Pemohon,<br>
                <strong>{{ $record->department->name ?? 'Unit Kerja' }}</strong>
                <div class="sig-space"></div>
                <strong>({{ $record->responsible_person }})</strong>
            </td>
            <td>
                Diverifikasi,<br>
                <strong>Kabag KKU</strong>
                <div class="sig-space"></div>
                <strong>(Indah Purnama Putri, S.Pd., M.Si)</strong>
            </td>
            <td>
                Disetujui,<br>
                <strong>Wakil Direktur II</strong>
                <div class="sig-space"></div>
                <strong>(Nur Asma Deli, ST., M.Si)</strong>
            </td>
        </tr>
        <tr>
            <td colspan="3" style="text-align: center; padding-top: 15px;">
                Menyetujui,<br>
                <strong>Direktur Politeknik Kampar</strong>
                <div class="sig-space"></div>
                <strong><u>Dr. Ir. Nina Veronika, ST., M.Sc., IPM</u></strong><br>
                NRP. 110806009
            </td>
        </tr>
    </table>

</body>

</html>
