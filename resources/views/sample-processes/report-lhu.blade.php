<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Laporan Pengujian Laboratorium - {{ $reportNumber }}</title>
    <style>
        :root{
            --ink:#0b1220;
            --muted:#5b6779;
            --accent:#123b66;
            --border:#D8DEE9;
            --paper:#ffffff;
            --brand:#0d3b66;
        }
        html,body{margin:0;padding:0;background:#f6f8fb;color:var(--ink);font:14px/1.58 "Inter",system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Helvetica Neue",Arial,"Noto Sans","Liberation Sans",sans-serif;}
        .page{
            width:210mm; min-height:297mm;
            margin: 12mm auto; padding: 16mm 14mm;
            background:var(--paper); box-shadow:0 6px 20px rgba(0,0,0,.08); border:1px solid var(--border);
            box-sizing:border-box;
        }
        header{display:grid;grid-template-columns:80px 1fr;gap:14px;align-items:center;margin-bottom:12px;padding-bottom:10px;border-bottom:2px solid var(--border);}
        .emblem{width:72px;height:72px;border:1px solid var(--border);border-radius:8px;display:grid;place-items:center;font-size:11px;color:var(--muted);}
        .org h1{font-size:14px;margin:0 0 2px 0;letter-spacing:.2px;text-transform:uppercase}
        .org .sub{font-size:12px;color:var(--muted);margin:0}
        .meta{display:flex;flex-wrap:wrap;gap:10px 18px;margin:8px 0 14px 0}
        .badge{font-weight:600;color:#fff;background:var(--brand);padding:4px 8px;border-radius:6px;font-size:12px}
        .small{font-size:12px;color:var(--muted)}
        h2{font-size:16px;margin:10px 0 8px 0}
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:10px 24px;margin:8px 0 10px}
        .field{display:flex;gap:10px}
        .label{flex:0 0 210px;color:var(--muted)}
        .value{flex:1}
        table{width:100%;border-collapse:collapse;margin:8px 0 6px 0}
        th,td{border:1px solid var(--border);padding:8px 10px;vertical-align:top}
        th{background:#f1f4f9;text-align:left}
        .disclaimer{font-size:12px;color:var(--muted);margin-top:6px}
        footer{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px}
        .sign{border:1px dashed var(--border);border-radius:10px;padding:12px}
        .sign .title{font-size:13px;font-weight:600;margin-bottom:8px}
        .verif{border:1px dashed var(--border);border-radius:10px;padding:12px;font-size:12px;display:grid;gap:6px}
    </style>
</head>
<body>
@php
    $sample = $sampleProcess->sample;
    $testRequest = $sample?->testRequest;
    $investigator = $testRequest?->investigator;
    $customerUnit = $investigator?->jurisdiction ?? $investigator?->name ?? '-';
    $customerName = trim(($investigator?->rank ? $investigator->rank . ' ' : '') . ($investigator?->name ?? ''));
    $customerAddress = $testRequest?->delivery_address ?? '-';
    $requestNumber = $testRequest?->request_number ?? '-';
    $caseNumber = $testRequest?->case_number ?? '-';
    $sampleName = $sample?->sample_name ?? '-';
    $sampleCode = $sample?->sample_code ?? '-';
    $sampleQuantity = $sample?->quantity;
    $sampleQuantityUnit = $sample?->quantity_unit;
    $quantityDisplay = $sampleQuantity ? rtrim(rtrim(number_format($sampleQuantity, 2, ',', '.'), '0'), ',') . ' ' . ($sampleQuantityUnit ?? '') : '-';
    $batchNumber = $sample?->batch_number ?? '-';
    $expiryDate = $sample?->expiry_date ? $sample->expiry_date->format('d F Y') : '-';
    $receivedDate = $testRequest?->received_at ? $testRequest->received_at->format('d F Y') : '-';
    $testDate = $sample?->test_date ? $sample->test_date->format('d F Y') : '-';
    $testsSummary = collect($sample?->test_methods ?? [])->map(fn($method) => is_string($method) ? ucfirst(str_replace('_', ' ', $method)) : $method)->implode(', ');
    $testResultPrefix = match($testResultRaw) {
        'positive' => '(+)',
        'negative' => '(-)',
        default => '',
    };
    $testResultText = trim($testResultPrefix . ' ' . ($detectedSubstance ?: $testResultLabel));
    $reportDate = $generatedAt->format('d F Y');
    $instrumentLabel = $instrument ?: ($sample?->test_type ?? '-');
    $verifierStub = [
        'Teknis' => '',
        'Mutu' => '',
        'Administrasi' => '',
    ];
@endphp
<section class="page">
    <header>
        <div class="emblem">Logo</div>
        <div class="org">
            <h1>FR/LPMF/7.8.3 - PUSAT KEDOKTERAN DAN KESEHATAN POLRI</h1>
            <p class="sub">LABORATORIUM PENGUJIAN MUTU FARMASI KEPOLISIAN</p>
            <p class="small">Jl. Cipinang Baru Raya No. 3B, Jakarta Timur 13240 &bull; Telp/Fax: 021-4700921 &bull; Email: farmapolpusdokkespolri@yahoo.com</p>
        </div>
    </header>

    <div class="meta">
        <span class="badge">LAPORAN PENGUJIAN LABORATORIUM</span>
        <span class="small"><b>Nomor:</b> <span class="code">{{ $reportNumber }}</span></span>
        <span class="page-num">Halaman 1/1</span>
    </div>

    <h2>Informasi Pelanggan &amp; Sampel</h2>
    <div class="grid">
        <div class="field"><div class="label">Nama Pelanggan</div><div class="value">{{ $customerUnit }}</div></div>
        <div class="field"><div class="label">Alamat Pelanggan</div><div class="value">{{ $customerAddress }}</div></div>
        <div class="field"><div class="label">Nama Kontak</div><div class="value">{{ $customerName ?: '-' }}</div></div>
        <div class="field"><div class="label">Nomor Permintaan</div><div class="value">{{ $requestNumber }}</div></div>
        <div class="field"><div class="label">Nomor Surat / Dasar</div><div class="value">{{ $caseNumber }}</div></div>
        <div class="field"><div class="label">Nama Sampel</div><div class="value">{{ $sampleName }}</div></div>
        <div class="field"><div class="label">Jumlah Sampel</div><div class="value">{{ $quantityDisplay }}</div></div>
        <div class="field"><div class="label">No Batch</div><div class="value">{{ $batchNumber }}</div></div>
        <div class="field"><div class="label">Exp. Date</div><div class="value">{{ $expiryDate }}</div></div>
        <div class="field"><div class="label">Tanggal Penerimaan Sampel</div><div class="value">{{ $receivedDate }}</div></div>
        <div class="field"><div class="label">Tanggal Pengujian</div><div class="value">{{ $testDate }}</div></div>
        <div class="field"><div class="label">Kode Sampel</div><div class="value">{{ $sampleCode }}</div></div>
    </div>

    <h2>Hasil Pengujian</h2>
    <table>
        <thead>
            <tr>
                <th>Parameter Uji</th>
                <th>Hasil</th>
                <th>Metode Uji</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Identifikasi</td>
                <td>{{ $testResultText }}</td>
                <td>{{ $instrumentLabel ?: '-' }}</td>
            </tr>
        </tbody>
    </table>
    @if($testsSummary)
        <div class="small">Metode pendukung: {{ $testsSummary }}</div>
    @endif

    <p class="disclaimer">Disclaimer: Hasil uji hanya berlaku untuk sampel yang diterima oleh laboratorium.</p>

    <footer>
        <div class="sign">
            <div class="title">Jakarta, {{ $reportDate }}</div>
            <div>Pusat Kedokteran dan Kesehatan Polri<br/>Laboratorium Pengujian Mutu Farmasi Kepolisian</div>
            <div style="margin:16px 0 10px 0; height:60px;"></div>
            <div><b>Kepala Laboratorium,</b><br/>___________________________</div>
        </div>
        <div>
            <div class="title">Paraf verifikator</div>
            <div class="verif">
                @foreach($verifierStub as $role => $signature)
                    <div>{{ $loop->iteration }}. {{ $role }} : {{ $signature }}</div>
                @endforeach
            </div>
        </div>
    </footer>
</section>
</body>
</html>
