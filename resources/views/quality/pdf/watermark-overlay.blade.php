<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Watermark</title>
    <style>
        @page {
            margin: 0;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            position: relative;
        }

        .watermark {
            position: fixed;
            top: 42%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 54px;
            letter-spacing: 6px;
            color: rgba(100, 116, 139, 0.16);
            white-space: nowrap;
        }
    </style>
</head>
<body>
<div class="watermark">{{ $watermarkText }}</div>
</body>
</html>
