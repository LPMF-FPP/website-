<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} - {{ $versionLabel }}</title>
    <style>
        @page {
            margin: 36px 40px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #111827;
            position: relative;
        }

        .watermark {
            position: fixed;
            top: 42%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 54px;
            letter-spacing: 6px;
            color: rgba(100, 116, 139, 0.2);
            z-index: -1;
            white-space: nowrap;
        }

        .header {
            margin-bottom: 20px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 10px;
        }

        .header h1 {
            margin: 0;
            font-size: 18px;
        }

        .meta {
            margin-top: 8px;
            color: #6b7280;
            font-size: 11px;
        }
    </style>
</head>
<body>
<div class="watermark">{{ $watermarkText }}</div>

<div class="header">
    <h1>{{ $title }}</h1>
    <div class="meta">Versi {{ $versionLabel }}</div>
</div>

{!! $contentHtml !!}
</body>
</html>
