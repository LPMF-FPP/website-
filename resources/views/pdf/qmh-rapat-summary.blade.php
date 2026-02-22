<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Hasil Rapat QMH</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #111827;
            line-height: 1.45;
        }

        h1,
        h2 {
            margin: 0;
        }

        .header {
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 1px solid #d1d5db;
        }

        .title {
            font-size: 18px;
            font-weight: 700;
        }

        .meta {
            margin-top: 6px;
            color: #374151;
        }

        .section {
            margin-top: 14px;
            padding: 10px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
        }

        .section-title {
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: 700;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #d1d5db;
            padding: 6px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f3f4f6;
            font-weight: 700;
        }

        .muted {
            color: #6b7280;
        }

        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 999px;
            background: #e5e7eb;
            font-size: 10px;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">Hasil Rapat QMH</div>
        <div class="meta">
            <div><strong>Judul:</strong> {{ $rapat->title }}</div>
            <div><strong>Jenis:</strong> {{ ucfirst(str_replace('_', ' ', (string) $rapat->meeting_type)) }}</div>
            <div><strong>Jadwal:</strong> {{ $rapat->scheduled_at?->format('d M Y H:i') ?? '-' }}</div>
            <div><strong>Lokasi:</strong> {{ $rapat->location ?: '-' }}</div>
            <div><strong>Status:</strong> <span class="badge">{{ strtoupper((string) $rapat->status) }}</span></div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Agenda</div>
        <div>{{ $rapat->agenda ?: 'Belum ada agenda.' }}</div>
    </div>

    <div class="section">
        <div class="section-title">Daftar Peserta</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 44px;">No</th>
                    <th>Nama</th>
                    <th style="width: 140px;">Status Kehadiran</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rapat->pesertas as $index => $peserta)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $peserta->user?->name ?? 'User tidak ditemukan' }}</td>
                        <td>{{ strtoupper(str_replace('_', ' ', (string) $peserta->attendance_status)) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="muted">Belum ada data peserta.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Notulensi</div>
        @forelse($rapat->notulensis as $notulensi)
            <div style="margin-bottom: 8px;">
                <strong>Versi {{ $notulensi->version }}</strong>
                <div>{{ $notulensi->content }}</div>
            </div>
        @empty
            <div class="muted">Belum ada notulensi.</div>
        @endforelse
    </div>

    <div class="section">
        <div class="section-title">Action Items</div>
        <table>
            <thead>
                <tr>
                    <th>Judul</th>
                    <th>PIC</th>
                    <th>Jatuh Tempo</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rapat->actionItems as $item)
                    <tr>
                        <td>{{ $item->title }}</td>
                        <td>{{ $item->assignee?->name ?? '-' }}</td>
                        <td>{{ $item->due_date?->format('d M Y') ?? '-' }}</td>
                        <td>{{ strtoupper((string) $item->status) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="muted">Belum ada action item.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 12px; font-size: 10px; color: #6b7280;">
        Dicetak otomatis pada {{ now()->format('d M Y H:i') }}
    </div>
</body>
</html>
