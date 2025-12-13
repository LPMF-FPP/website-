<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Surat Pengantar</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 30px; }
        .logo { width: 80px; height: auto; }
        .title { font-weight: bold; font-size: 16px; margin: 10px 0; }
        .content { margin: 20px 0; }
        .signature { margin-top: 50px; text-align: right; }
        .signature-box { width: 200px; height: 100px; border: 1px solid #000; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; }
        table, th, td { border: 1px solid black; }
        th, td { padding: 8px; text-align: left; }
    </style>
</head>
<body>
    <div class="header">
        {{-- <img src="{{ public_path('images/logo-polri.png') }}" class="logo" alt="Logo Polri"> --}}
        <div class="title">KEPOLISIAN NEGARA REPUBLIK INDONESIA</div>
        <div class="title">PUSAT KEDOKTERAN DAN KESEHATAN POLRI</div>
        <div>SUB SATKER FARMAPOL</div>
    </div>

    <div class="content">
        <h3 style="text-align: center;">SURAT PENGANTAR</h3>

        <p>Kepada Yth,<br>
        {{ $request->investigator->rank }} {{ $request->investigator->name }}<br>
        {{ $request->investigator->jurisdiction }}</p>

        <p>Dengan hormat,</p>

        <p>Bersama ini kami sampaikan hasil pengujian laboratorium untuk:</p>

        <table>
            <tr>
                <td><strong>No. Permintaan</strong></td>
                <td>{{ $request->request_number ?? 'REQ-' . str_pad($request->id, 6, '0', STR_PAD_LEFT) }}</td>
            </tr>
            <tr>
                <td><strong>Tanggal Permintaan</strong></td>
                <td>{{ $request->created_at->format('d F Y') }}</td>
            </tr>
            <tr>
                <td><strong>Tersangka</strong></td>
                <td>{{ $request->suspect_name }}</td>
            </tr>
            <tr>
                <td><strong>Jumlah Sampel</strong></td>
                <td>{{ $request->samples->count() }} sampel</td>
            </tr>
        </table>

        <p>Demikian surat pengantar ini kami sampaikan. Atas perhatian dan kerjasamanya kami ucapkan terima kasih.</p>
    </div>

    <div class="signature">
        <p>Jakarta, {{ date('d F Y') }}</p>
        <p><strong>KEPALA FARMAPOL<br>PUSDOKKES POLRI</strong></p>

        <div class="signature-box"></div>

        <p><strong>Dr. [NAMA KAFARMAPOL]</strong><br>
        KOMPOL NRP [NRP]</p>
    </div>
</body>
</html>
