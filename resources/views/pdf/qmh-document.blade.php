@php
    /** @var \App\Models\QmhDocumentRevision $revision */
    $document = $revision->document;

    $logoSrc = is_string($logoSrc ?? null) ? $logoSrc : '';
    if ($logoSrc === '') {
        $logoPath = public_path('images/logo-pusdokkes-polri.png');
        if (file_exists($logoPath)) {
            $logoSrc = $logoPath;
        }
    }

    $letterheadLabName = settings('branding.lab_name', 'LABORATORIUM PENGUJIAN MUTU FARMASI KEPOLISIAN');

    $docTypeLabel = match ((string) ($document->doc_type ?? '')) {
        'sop' => 'PROSEDUR',
        'ik' => 'INSTRUKSI KERJA',
        'formulir' => 'FORMULIR',
        'fr' => 'FORMULIR',
        default => 'DOKUMEN',
    };

    $effectiveDate = ($revision->effective_date && $revision->status === 'published')
        ? $revision->effective_date->format('d-m-Y')
        : '-';
    $statusLabel = strtoupper((string) ($revision->status ?? ''));
    $versionLabelRaw = (string) ($revision->version_label ?? sprintf('E%d-R%d', (int) $revision->edition_number, (int) $revision->revision_number));
    $versionLabel = str_replace('-', '/', $versionLabelRaw);
    $headerTitle = strtoupper((string) ($document?->title ?? 'JUDUL PROSEDUR'));

    if (in_array((string) ($document?->doc_type ?? ''), ['formulir', 'fr'], true)) {
        $headerTitle = preg_replace('/\bFR\s*NON\s*TABLE\b/i', '', $headerTitle) ?? $headerTitle;
        $headerTitle = trim((string) preg_replace('/\s{2,}/', ' ', $headerTitle));
        if ($headerTitle === '') {
            $headerTitle = 'FORMULIR';
        }
    }

    $schema = is_array($schema ?? null) ? $schema : ['questions' => []];
    $questions = is_array($schema['questions'] ?? null) ? $schema['questions'] : [];
    $answers = is_array($answers ?? null) ? $answers : (is_array($revision->answers_json ?? null) ? $revision->answers_json : []);
    $answers = \App\Support\QmhAnswerSanitizer::sanitizeAnswersJson($answers);

    $layoutConfig = is_array($layoutConfig ?? null) ? $layoutConfig : [];
    $layoutProfile = \App\Support\QmhFrLayoutProfile::normalizeRuntimeProfile(
        is_string($layoutProfile ?? null) ? $layoutProfile : (string) ($layoutConfig['layout_profile'] ?? 'legacy')
    );
    $shellMode = \App\Support\QmhFrLayoutProfile::normalizeShellMode(
        is_string($layoutConfig['shell_mode'] ?? null) ? $layoutConfig['shell_mode'] : null
    );
    $showSignoffFooter = isset($showSignoffFooter)
        ? (bool) $showSignoffFooter
        : \App\Support\QmhFrLayoutProfile::normalizeShowSignoffFooter($layoutConfig['show_signoff_footer'] ?? true);

    $isFrDocument = in_array((string) ($document?->doc_type ?? ''), ['formulir', 'fr'], true);
    $renderFrHeaderFooter = ! $isFrDocument || \App\Support\QmhFrLayoutProfile::shouldRenderFrShellFromPolicy($shellMode);
    $useMinimalFrShell = $isFrDocument && $layoutProfile === 'structured_form';

    $declarationHeader = is_string($layoutConfig['declaration_header'] ?? null)
        ? trim($layoutConfig['declaration_header'])
        : '';
    $riskMatrixColumns = \App\Support\QmhFrLayoutProfile::normalizeRiskMatrixColumns($layoutConfig['risk_matrix_columns'] ?? null);

    $hasStructuredAnswers = false;
    if (is_array($answers) && count($answers) > 0) {
        foreach ($answers as $val) {
            if (is_string($val) && \App\Support\QmhAnswerSanitizer::plainText($val) !== '') {
                $hasStructuredAnswers = true;
                break;
            }
            if (is_array($val) && count($val) > 0) {
                foreach ($val as $item) {
                    if (! is_string($item)) {
                        continue;
                    }

                    if (\App\Support\QmhAnswerSanitizer::plainText($item) !== '') {
                        $hasStructuredAnswers = true;
                        break 2;
                    }
                }
            }
        }
    }

    $createdBy = $revision->createdBy;
    $reviewedBy = $revision->reviewedBy;
    $approvedBy = $revision->approvedBy;

    $signerNameRank = static function ($user): string {
        if (! $user) {
            return '-';
        }

        $name = trim((string) ($user->name ?? ''));
        $rank = trim((string) ($user->rank ?? ''));

        $namePart = $name !== '' ? $name : '-';
        $rankPart = $rank !== '' ? $rank : '-';

        return sprintf('%s/%s', $namePart, $rankPart);
    };

    $signerPosition = static function ($user): string {
        if (! $user) {
            return '-';
        }

        $jabatan = trim((string) data_get($user, 'jabatan', ''));
        if ($jabatan !== '') {
            return $jabatan;
        }

        $role = trim((string) ($user->role ?? ''));

        return match ($role) {
            'manajer_teknis' => 'Manajer Teknis',
            'penyelia' => 'Penyelia',
            'analis' => 'Analis',
            'supervisor' => 'Supervisor',
            'admin' => 'Admin',
            default => $role !== '' ? ucwords(str_replace('_', ' ', $role)) : '-',
        };
    };

    $createdNameRank = $signerNameRank($createdBy);
    $reviewedNameRank = $signerNameRank($reviewedBy);
    $approvedNameRank = $signerNameRank($approvedBy);
    $createdPosition = $signerPosition($createdBy);
    $reviewedPosition = $signerPosition($reviewedBy);
    $approvedPosition = $signerPosition($approvedBy);

    $redNotice = (string) ($redNotice ?? 'Isi Dokumen ini tidak diperkenankan untuk disalin atau digandakan tanpa persetujuan dari Kepala Farmasi Kepolisian Pusdokkes Polri');
    $resolvedPageCount = isset($resolvedPageCount) && is_int($resolvedPageCount) && $resolvedPageCount > 0 ? $resolvedPageCount : null;
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $document?->doc_code ?? 'QMH' }} - {{ $versionLabel }}</title>
    <style>
        @page {
            margin: {{ $renderFrHeaderFooter ? ($useMinimalFrShell ? '78px 36px 64px 36px' : '206px 36px 194px 36px') : '36px 36px 36px 36px' }};
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            line-height: 1.45;
            color: #111827;
            margin: 0;
            padding: 0;
        }

        .watermark {
            position: fixed;
            top: 45%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 52px;
            letter-spacing: 6px;
            color: rgba(100, 116, 139, 0.18);
            z-index: 0;
            white-space: nowrap;
            pointer-events: none;
            user-select: none;
        }

        header {
            position: fixed;
            top: {{ $useMinimalFrShell ? '-66px' : '-152px' }};
            left: 0;
            right: 0;
            height: {{ $useMinimalFrShell ? '60px' : '144px' }};
        }

        footer {
            position: fixed;
            bottom: {{ $useMinimalFrShell ? '-54px' : '-172px' }};
            left: 0;
            right: 0;
            height: {{ $useMinimalFrShell ? '48px' : '164px' }};
            font-size: 9px;
            color: #111827;
        }

        .fr-minimal-header {
            width: 100%;
            border-bottom: 1px solid #111827;
            padding: 2px 0 4px 0;
            margin-top: 1px;
        }

        .fr-minimal-header-row {
            display: table;
            width: 100%;
            table-layout: fixed;
            white-space: nowrap;
        }

        .fr-minimal-logo-cell,
        .fr-minimal-text-cell {
            display: table-cell;
            vertical-align: middle;
        }

        .fr-minimal-logo-cell {
            width: 24px;
            text-align: left;
            line-height: 1;
            padding-right: 4px;
        }

        .fr-minimal-text-cell {
            width: auto;
        }

        .fr-minimal-logo {
            width: 20px;
            height: auto;
            display: inline-block;
        }

        .fr-minimal-text {
            font-size: 6.1px;
            font-weight: 700;
            letter-spacing: 0;
            text-transform: uppercase;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.2;
        }

        .sop-body-offset {
            margin-top: 44px;
        }

        .doc-header {
            width: 100%;
            border-collapse: collapse;
        }

        .doc-header td {
            border: 1px solid #111827;
            vertical-align: middle;
            padding: 6px 8px;
        }

        .header-left {
            width: 34%;
            text-align: center;
            padding: 8px;
        }

        .header-left .logo {
            width: 54px;
            height: auto;
            margin: 0 auto 4px auto;
            display: block;
        }

        .header-left .org {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .header-center {
            width: 34%;
            text-align: center;
        }

        .header-center .type {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin: 0;
        }

        .header-center .title {
            margin-top: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .header-right {
            width: 32%;
            padding: 0;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
        }

        .meta-table td {
            border: 1px solid #111827;
            padding: 4px 6px;
            font-size: 10px;
        }

        .meta-label {
            width: 55%;
        }

        .meta-value {
            width: 45%;
            text-align: left;
        }

        main {
            font-size: 11px;
        }

        .page-number::before {
            content: counter(page);
        }

        .footer-page {
            margin-top: 2px;
            text-align: right;
            color: #374151;
        }

        .qmh-section {
            margin: 0 0 10px 0;
        }

        .qmh-question {
            font-weight: 700;
            margin: 0 0 4px 0;
        }

        .qmh-answer {
            margin: 0;
        }

        .qmh-answer p {
            margin: 0;
        }

        .qmh-answer ul,
        .qmh-answer ol {
            margin: 0;
            padding-left: 16px;
            list-style-position: outside;
        }

        .qmh-answer ul {
            list-style-type: disc;
        }

        .qmh-answer ol {
            list-style-type: decimal;
        }

        .qmh-answer li {
            margin: 0;
        }

        .qmh-list {
            margin: 0;
            padding-left: 16px;
            list-style-type: disc;
        }

        .form-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        .form-table td {
            border: 1px solid #111827;
            padding: 6px 8px;
            vertical-align: top;
        }

        .form-no {
            width: 6%;
            text-align: center;
            font-weight: 700;
        }

        .form-label {
            width: 28%;
            font-weight: 700;
            text-transform: uppercase;
        }

        .form-value {
            width: 66%;
        }

        .form-section {
            background: #f3f4f6;
            font-weight: 700;
            text-transform: uppercase;
            text-align: center;
        }

        .form-row--sm .qmh-answer {
            min-height: 18px;
        }

        .form-row--md .qmh-answer {
            min-height: 42px;
        }

        .form-row--lg .qmh-answer {
            min-height: 72px;
        }

        .fr-section-title {
            margin: 10px 0 4px 0;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            border-bottom: 1px solid #111827;
            padding-bottom: 2px;
        }

        .risk-matrix-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        .risk-matrix-table th,
        .risk-matrix-table td {
            border: 1px solid #111827;
            padding: 6px 8px;
            vertical-align: top;
        }

        .risk-matrix-table th {
            background: #f3f4f6;
            text-transform: uppercase;
            font-size: 10px;
            text-align: center;
        }

        .signoff {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }

        .signoff th,
        .signoff td {
            border: 1px solid #111827;
            padding: 3px 5px;
            vertical-align: top;
            font-size: 8.5px;
        }

        .signoff th {
            font-weight: 700;
            text-align: center;
        }

        .signoff .row-label {
            width: 28%;
            font-weight: 700;
        }

        .notice {
            margin-top: 2px;
            text-align: center;
            color: #b91c1c;
            font-style: italic;
            font-size: 8.5px;
        }

        .fr-minimal-footer {
            border-top: 1px solid #111827;
            padding-top: 4px;
        }

        .fr-minimal-footer .notice {
            margin-top: 0;
            margin-bottom: 2px;
        }
    </style>
</head>
<body>
<div class="watermark">{{ $watermarkText ?? '' }}</div>

@if($renderFrHeaderFooter)
    <header>
        @if($useMinimalFrShell)
            <div class="fr-minimal-header">
                <div class="fr-minimal-header-row">
                    <div class="fr-minimal-logo-cell">
                        @if($logoSrc)
                            <img class="fr-minimal-logo" src="{{ $logoSrc }}" alt="Logo">
                        @endif
                    </div>
                    <div class="fr-minimal-text-cell">
                        <div class="fr-minimal-text">
                            {{ strtoupper((string) ($document?->doc_code ?? '-')) }}|{{ $headerTitle }}|{{ $statusLabel }}|{{ $versionLabel }}|{{ $effectiveDate }}
                        </div>
                    </div>
                </div>
            </div>
        @else
            <table class="doc-header">
                <tr>
                    <td class="header-left">
                        @if($logoSrc)
                            <img class="logo" src="{{ $logoSrc }}" alt="Logo">
                        @endif
                        <div class="org">{!! nl2br(e($letterheadLabName)) !!}</div>
                    </td>
                    <td class="header-center">
                        <div class="type">{{ $docTypeLabel }}</div>
                        <div class="title">{{ $headerTitle }}</div>
                    </td>
                    <td class="header-right">
                        <table class="meta-table">
                            <tr>
                                <td class="meta-label">No. Dokumen</td>
                                <td class="meta-value">{{ $document?->doc_code ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="meta-label">Edisi/Revisi</td>
                                <td class="meta-value">{{ $versionLabel }}</td>
                            </tr>
                            <tr>
                                <td class="meta-label">Tgl. Efektif</td>
                                <td class="meta-value">{{ $effectiveDate }}</td>
                            </tr>
                            <tr>
                                <td class="meta-label">Status</td>
                                <td class="meta-value">{{ $statusLabel }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        @endif
    </header>

    <footer>
        @if($useMinimalFrShell)
            <div class="fr-minimal-footer">
                <div class="notice">{{ $redNotice }}</div>
                <div class="footer-page">Halaman <span class="page-number"></span>/{{ $resolvedPageCount ?? '-' }}</div>
            </div>
        @else
            @if($showSignoffFooter)
                <table class="signoff">
                    <tr>
                        <th>&nbsp;</th>
                        <th>Dibuat Oleh:</th>
                        <th>Diperiksa Oleh:</th>
                        <th>Disahkan Oleh:</th>
                    </tr>
                    <tr>
                        <td class="row-label">Nama/Pangkat</td>
                        <td>{{ $createdNameRank }}</td>
                        <td>{{ $reviewedNameRank }}</td>
                        <td>{{ $approvedNameRank }}</td>
                    </tr>
                    <tr>
                        <td class="row-label">Tanda Tangan</td>
                        <td style="height: 18px">&nbsp;</td>
                        <td style="height: 18px">&nbsp;</td>
                        <td style="height: 18px">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="row-label">Jabatan</td>
                        <td>{{ $createdPosition }}</td>
                        <td>{{ $reviewedPosition }}</td>
                        <td>{{ $approvedPosition }}</td>
                    </tr>
                </table>
            @endif

            <div class="notice">{{ $redNotice }}</div>

            <div class="footer-page">Halaman <span class="page-number"></span>/{{ $resolvedPageCount ?? '-' }}</div>
        @endif

    </footer>
@endif

<main>
    @if(($document?->doc_type ?? '') === 'ik')
        @php
            $parentSop = $document?->parentSop;
            $parentSopLabel = $parentSop ? trim(($parentSop->doc_code ?? '').' - '.($parentSop->title ?? '')) : '';

            $getAnswer = function (string $key) use ($answers): string {
                $val = $answers[$key] ?? '';
                if (! is_string($val)) {
                    return '';
                }

                return trim($val);
            };

            $renderAnswer = function (string $key) use ($getAnswer): string {
                $raw = $getAnswer($key);
                if ($raw === '' || \App\Support\QmhAnswerSanitizer::plainText($raw) === '') {
                    return '<p class="qmh-answer">-</p>';
                }

                if (\App\Support\QmhAnswerSanitizer::looksLikeHtml($raw)) {
                    return '<div class="qmh-answer">'.$raw.'</div>';
                }

                return '<p class="qmh-answer">'.nl2br(e($raw)).'</p>';
            };

            $referenceValue = $getAnswer('reference');
            if ($referenceValue === '' && $parentSopLabel !== '') {
                $referenceValue = sprintf(
                    'PROSEDUR %s [%s]',
                    (string) ($parentSop->doc_code ?? ''),
                    (string) ($parentSop->title ?? '')
                );
            }

            $closingDefault = 'Instruksi kerja ini harus ditinjau dan diperbaharui setiap tahun untuk memastikan kesesuaiannya dengan kebutuhan operasional dan standar yang berlaku. Semua perubahan atau pembaruan harus disetujui oleh manajemen dan didokumentasikan dengan baik.';
            $closingValue = $getAnswer('closing');
        @endphp

        <div class="qmh-section">
            <div class="qmh-question">1. TUJUAN</div>
            {!! $renderAnswer('purpose') !!}
        </div>

        <div class="qmh-section">
            <div class="qmh-question">2. RUANG LINGKUP</div>
            {!! $renderAnswer('scope') !!}
        </div>

        <div class="qmh-section">
            <div class="qmh-question">3. TANGGUNG JAWAB</div>
            @php
                $responsibility = $getAnswer('responsibilities');
            @endphp
            @if($responsibility === '' || \App\Support\QmhAnswerSanitizer::plainText($responsibility) === '')
                <p class="qmh-answer">JABATAN LABORATORIUM</p>
            @else
                {!! $renderAnswer('responsibilities') !!}
            @endif
        </div>

        <div class="qmh-section">
            <div class="qmh-question">4. ACUAN</div>
            @if(trim($referenceValue) === '')
                <p class="qmh-answer">-</p>
            @else
                @php $refIsHtml = \App\Support\QmhAnswerSanitizer::looksLikeHtml($referenceValue); @endphp
                @if($refIsHtml)
                    <div class="qmh-answer">{!! $referenceValue !!}</div>
                @else
                    <p class="qmh-answer">{!! nl2br(e($referenceValue)) !!}</p>
                @endif
            @endif
        </div>

        <div class="qmh-section">
            <div class="qmh-question">5. INSTRUKSI KERJA</div>
            {!! $renderAnswer('instructions') !!}
        </div>

        <div class="qmh-section">
            <div class="qmh-question">6. DOKUMENTASI YANG DIPERLUKAN</div>
            @php
                $docs = $answers['required_docs'] ?? null;
            @endphp
            @if(is_string($docs))
                @if(trim($docs) === '' || \App\Support\QmhAnswerSanitizer::plainText($docs) === '')
                    <p class="qmh-answer">-</p>
                @else
                    <div class="qmh-answer">{!! $docs !!}</div>
                @endif
            @elseif(is_array($docs) && count($docs) > 0)
                <ul class="qmh-list">
                    @foreach($docs as $item)
                        @php $itemText = is_string($item) ? trim($item) : ''; @endphp
                        @if($itemText !== '' && \App\Support\QmhAnswerSanitizer::plainText($itemText) !== '')
                            <li>
                                @if(\App\Support\QmhAnswerSanitizer::looksLikeHtml($itemText))
                                    <div class="qmh-answer">{!! $itemText !!}</div>
                                @else
                                    {{ $itemText }}
                                @endif
                            </li>
                        @endif
                    @endforeach
                </ul>
            @else
                <p class="qmh-answer">-</p>
            @endif
        </div>

        <div class="qmh-section">
            <div class="qmh-question">7. PENUTUP</div>
            @if($closingValue === '' || \App\Support\QmhAnswerSanitizer::plainText($closingValue) === '')
                <p class="qmh-answer"><strong>{{ $closingDefault }}</strong></p>
            @elseif(\App\Support\QmhAnswerSanitizer::looksLikeHtml($closingValue))
                <div class="qmh-answer"><strong>{!! $closingValue !!}</strong></div>
            @else
                <p class="qmh-answer"><strong>{!! nl2br(e($closingValue)) !!}</strong></p>
            @endif
        </div>
    @elseif(in_array((string) ($document?->doc_type ?? ''), ['formulir', 'fr'], true))
        @php
            $formQuestions = $questions;

            $coerceBoolean = function (mixed $value): ?bool {
                if ($value === null) {
                    return null;
                }

                if (is_bool($value)) {
                    return $value;
                }

                if (is_int($value)) {
                    return $value === 1;
                }

                if (is_string($value)) {
                    $v = strtolower(trim($value));
                    if (in_array($v, ['1', 'true', 'on', 'yes', 'y'], true)) {
                        return true;
                    }
                    if (in_array($v, ['0', 'false', 'off', 'no', 'n', ''], true)) {
                        return false;
                    }
                }

                return null;
            };

            $renderCell = function (mixed $val, string $type = 'text', array $question = []) use ($coerceBoolean): string {
                if ($type === 'checkbox') {
                    $bool = $coerceBoolean($val);
                    if ($bool === null) {
                        return '<div class="qmh-answer">&nbsp;</div>';
                    }

                    return $bool
                        ? '<div class="qmh-answer"><strong>YA</strong></div>'
                        : '<div class="qmh-answer">TIDAK</div>';
                }

                if ($type === 'select') {
                    $value = is_string($val) ? trim($val) : (is_scalar($val) ? trim((string) $val) : '');
                    if ($value === '') {
                        return '<div class="qmh-answer">&nbsp;</div>';
                    }

                    $options = is_array($question['options'] ?? null) ? $question['options'] : [];
                    $label = $value;
                    foreach ($options as $opt) {
                        if (! is_array($opt)) {
                            continue;
                        }
                        $optValue = isset($opt['value']) && is_string($opt['value']) ? trim($opt['value']) : '';
                        if ($optValue === '' || $optValue !== $value) {
                            continue;
                        }
                        $optLabel = isset($opt['label']) && is_string($opt['label']) ? trim($opt['label']) : '';
                        $label = $optLabel !== '' ? $optLabel : $value;
                        break;
                    }

                    return '<div class="qmh-answer">'.e($label).'</div>';
                }

                if ($type === 'date' || $type === 'number') {
                    $value = is_string($val) ? trim($val) : (is_scalar($val) ? trim((string) $val) : '');
                    if ($value === '') {
                        return '<div class="qmh-answer">&nbsp;</div>';
                    }

                    return '<div class="qmh-answer">'.e($value).'</div>';
                }

                if ($val === null) {
                    return '<div class="qmh-answer">&nbsp;</div>';
                }

                if (is_string($val)) {
                    $raw = trim($val);
                    if ($raw === '' || \App\Support\QmhAnswerSanitizer::plainText($raw) === '') {
                        return '<div class="qmh-answer">&nbsp;</div>';
                    }

                    if (\App\Support\QmhAnswerSanitizer::looksLikeHtml($raw)) {
                        return '<div class="qmh-answer">'.$raw.'</div>';
                    }

                    return '<div class="qmh-answer">'.nl2br(e($raw)).'</div>';
                }

                if (is_array($val) && $type === 'list') {
                    $items = array_values(array_filter($val, fn ($item) => is_string($item) && trim($item) !== ''));
                    if (count($items) === 0) {
                        return '<div class="qmh-answer">&nbsp;</div>';
                    }

                    $html = '<ul class="qmh-list">';
                    foreach ($items as $item) {
                        $raw = trim((string) $item);
                        if ($raw === '' || \App\Support\QmhAnswerSanitizer::plainText($raw) === '') {
                            continue;
                        }

                        if (\App\Support\QmhAnswerSanitizer::looksLikeHtml($raw)) {
                            $html .= '<li><div class="qmh-answer">'.$raw.'</div></li>';
                        } else {
                            $html .= '<li>'.e($raw).'</li>';
                        }
                    }
                    $html .= '</ul>';

                    return $html;
                }

                return '<div class="qmh-answer">'.e(json_encode($val)).'</div>';
            };

            $rowHeightClass = function (string $type): string {
                return match ($type) {
                    'section' => 'form-row--sm',
                    'textarea' => 'form-row--lg',
                    'list' => 'form-row--md',
                    default => 'form-row--sm',
                };
            };

            $matrixColumnCount = count($riskMatrixColumns);
        @endphp

        @if(count($formQuestions) === 0)
            <div class="qmh-answer">Form schema belum diatur untuk formulir ini.</div>
        @elseif($layoutProfile === 'risk_matrix')
            <table class="risk-matrix-table">
                <thead>
                <tr>
                    @foreach($riskMatrixColumns as $column)
                        <th>{{ strtoupper($column) }}</th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @foreach($formQuestions as $q)
                    @php
                        $qid = (string) ($q['id'] ?? '');
                        $label = (string) ($q['label'] ?? $qid);
                        $type = (string) ($q['type'] ?? 'text');
                        $val = $qid !== '' ? ($answers[$qid] ?? null) : null;
                        $hint = trim((string) ($q['help'] ?? $q['placeholder'] ?? ''));
                    @endphp
                    @if($type === 'section')
                        <tr>
                            <td class="form-section" colspan="{{ $matrixColumnCount }}">{{ strtoupper($label) }}</td>
                        </tr>
                    @else
                        <tr>
                            <td class="form-label">{{ strtoupper($label) }}</td>
                            <td class="form-value">{!! $renderCell($val, $type, is_array($q) ? $q : []) !!}</td>
                            @for($colIndex = 2; $colIndex < $matrixColumnCount; $colIndex++)
                                <td class="form-value">{!! $colIndex === 2 && $hint !== '' ? e($hint) : '&nbsp;' !!}</td>
                            @endfor
                        </tr>
                    @endif
                @endforeach
                </tbody>
            </table>
        @elseif(in_array($layoutProfile, ['structured_form', 'declaration'], true))
            <table class="form-table">
                @foreach($formQuestions as $idx => $q)
                    @php
                        $qid = (string) ($q['id'] ?? '');
                        $label = (string) ($q['label'] ?? $qid);
                        $type = (string) ($q['type'] ?? 'text');
                        $val = $qid !== '' ? ($answers[$qid] ?? null) : null;
                    @endphp
                    <tr class="{{ $rowHeightClass($type) }}">
                        <td class="form-no">{{ $idx + 1 }}</td>
                        @if($type === 'section')
                            <td class="form-section" colspan="2">{{ strtoupper($label) }}</td>
                        @else
                            <td class="form-label">{{ strtoupper($label) }}</td>
                            <td class="form-value">{!! $renderCell($val, $type, is_array($q) ? $q : []) !!}</td>
                        @endif
                    </tr>
                @endforeach
            </table>
        @else
            <table class="form-table">
                @foreach($formQuestions as $idx => $q)
                    @php
                        $qid = (string) ($q['id'] ?? '');
                        $label = (string) ($q['label'] ?? $qid);
                        $type = (string) ($q['type'] ?? 'text');
                        $val = $qid !== '' ? ($answers[$qid] ?? null) : null;
                    @endphp
                    <tr class="{{ $rowHeightClass($type) }}">
                        <td class="form-no">{{ $idx + 1 }}</td>
                        @if($type === 'section')
                            <td class="form-section" colspan="2">{{ strtoupper($label) }}</td>
                        @else
                            <td class="form-label">{{ strtoupper($label) }}</td>
                            <td class="form-value">{!! $renderCell($val, $type, is_array($q) ? $q : []) !!}</td>
                        @endif
                    </tr>
                @endforeach
            </table>
        @endif
    @elseif(
        count($questions) > 0
        && (
            $hasStructuredAnswers
            || (string) ($document?->doc_type ?? '') === 'sop'
        )
    )
        @if((string) ($document?->doc_type ?? '') === 'sop')
            <div class="sop-body-offset">
        @endif
        @foreach($questions as $idx => $q)
            @php
                $qid = (string) ($q['id'] ?? '');
                $label = (string) ($q['label'] ?? $qid);
                $type = (string) ($q['type'] ?? 'text');
                $val = $qid !== '' ? ($answers[$qid] ?? null) : null;
            @endphp
            <div class="qmh-section">
                <div class="qmh-question">{{ ($idx + 1).'. '.$label }}</div>
                @if($type === 'list')
                    @if(is_string($val))
                        @php
                            $rawList = trim((string) $val);
                            $isHtmlList = $rawList !== '' && \App\Support\QmhAnswerSanitizer::looksLikeHtml($rawList);
                            $hasListContainer = $isHtmlList && preg_match('/<(ol|ul)\b/i', $rawList) === 1;
                        @endphp

                        @if($rawList === '' || \App\Support\QmhAnswerSanitizer::plainText($rawList) === '')
                            <p class="qmh-answer">-</p>
                        @elseif($hasListContainer)
                            <div class="qmh-answer">{!! $rawList !!}</div>
                        @else
                            <ul class="qmh-list">
                                <li>
                                    @if($isHtmlList)
                                        <div class="qmh-answer">{!! $rawList !!}</div>
                                    @else
                                        {{ $rawList }}
                                    @endif
                                </li>
                            </ul>
                        @endif
                    @else
                        @php
                            $items = is_array($val) ? $val : [];
                        @endphp
                        @if(count($items) > 0)
                            <ul class="qmh-list">
                                @foreach($items as $item)
                                    @php
                                        $itemText = is_string($item) ? $item : (is_scalar($item) ? (string) $item : json_encode($item));
                                        $itemText = trim((string) $itemText);
                                        $isHtml = $itemText !== '' && \App\Support\QmhAnswerSanitizer::looksLikeHtml($itemText);
                                    @endphp
                                    <li>
                                        @if($itemText === '' || \App\Support\QmhAnswerSanitizer::plainText($itemText) === '')
                                            -
                                        @elseif($isHtml)
                                            <div class="qmh-answer">{!! $itemText !!}</div>
                                        @else
                                            {{ $itemText }}
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="qmh-answer">-</p>
                        @endif
                    @endif
                @else
                    @php
                        $raw = is_string($val) ? $val : ($val === null ? '' : (is_scalar($val) ? (string) $val : json_encode($val)));
                        $raw = trim((string) $raw);
                        $isHtml = $raw !== '' && \App\Support\QmhAnswerSanitizer::looksLikeHtml($raw);
                    @endphp
                    @if($raw === '' || \App\Support\QmhAnswerSanitizer::plainText($raw) === '')
                        <p class="qmh-answer">-</p>
                    @elseif($isHtml)
                        <div class="qmh-answer">{!! $raw !!}</div>
                    @else
                        <p class="qmh-answer">{!! nl2br(e($raw)) !!}</p>
                    @endif
                @endif
            </div>
        @endforeach
        @if((string) ($document?->doc_type ?? '') === 'sop')
            </div>
        @endif
    @else
        {!! \App\Support\QmhHtmlSanitizer::sanitize($contentHtml ?? ($revision->content_html ?: '<p>Konten dokumen belum tersedia.</p>')) !!}
    @endif
</main>

</body>
</html>
