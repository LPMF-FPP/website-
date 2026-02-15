@php
    /** @var \App\Models\QmhDocumentRevision $revision */
    $document = $revision->document;

    $logoPath = public_path('images/logo-pusdokkes-polri.png');
    $logoSrc = file_exists($logoPath) ? $logoPath : '';

    $docTypeLabel = match ((string) ($document->doc_type ?? '')) {
        'sop' => 'PROSEDUR',
        'ik' => 'INSTRUKSI KERJA',
        'formulir' => 'FORMULIR',
        'fr' => 'FORMULIR',
        default => 'DOKUMEN',
    };

    $effectiveDate = $revision->effective_date?->format('d-m-Y') ?? '-';
    $statusLabel = strtoupper((string) ($revision->status ?? ''));
    $versionLabelRaw = (string) ($revision->version_label ?? sprintf('E%d-R%d', (int) $revision->edition_number, (int) $revision->revision_number));
    $versionLabel = str_replace('-', '/', $versionLabelRaw);

    $schema = is_array($schema ?? null) ? $schema : ['questions' => []];
    $questions = is_array($schema['questions'] ?? null) ? $schema['questions'] : [];
    $answers = is_array($answers ?? null) ? $answers : (is_array($revision->answers_json ?? null) ? $revision->answers_json : []);
    $answers = \App\Support\QmhAnswerSanitizer::sanitizeAnswersJson($answers);

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

    $redNotice = (string) ($redNotice ?? 'Isi Dokumen ini tidak diperkenankan untuk disalin atau digandakan tanpa persetujuan dari Kepala Farmasi Kepolisian Pusdokkes Polri');
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $document?->doc_code ?? 'QMH' }} - {{ $versionLabel }}</title>
    <style>
        @page {
            margin: 130px 40px 160px 40px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            line-height: 1.45;
            color: #111827;
        }

        .watermark {
            position: fixed;
            top: 45%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 52px;
            letter-spacing: 6px;
            color: rgba(100, 116, 139, 0.18);
            z-index: -1;
            white-space: nowrap;
        }

        header {
            position: fixed;
            top: -110px;
            left: 0;
            right: 0;
            height: 105px;
        }

        footer {
            position: fixed;
            bottom: -140px;
            left: 0;
            right: 0;
            height: 130px;
            font-size: 9px;
            color: #111827;
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
            font-size: 10px;
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

        .signoff {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        .signoff th,
        .signoff td {
            border: 1px solid #111827;
            padding: 5px 6px;
            vertical-align: top;
            font-size: 9px;
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
            text-align: center;
            color: #b91c1c;
            font-style: italic;
        }
    </style>
</head>
<body>
<div class="watermark">{{ $watermarkText ?? '' }}</div>

<header>
    <table class="doc-header">
        <tr>
            <td class="header-left">
                @if($logoSrc)
                    <img class="logo" src="{{ $logoSrc }}" alt="Logo">
                @endif
                <div class="org">LABORATORIUM PENGUJIAN MUTU<br>FARMAPOL PUSDOKKES POLRI</div>
            </td>
            <td class="header-center">
                <div class="type">{{ $docTypeLabel }}</div>
                <div class="title">[{{ strtoupper((string) ($document?->title ?? 'JUDUL PROSEDUR')) }}]</div>
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
                        <td class="meta-label">Halaman</td>
                        <td class="meta-value">1 DARI X</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Status</td>
                        <td class="meta-value">{{ $statusLabel }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->get_font('DejaVu Sans', 'normal');
            // Header page count (approx. in the Halaman value cell)
            $pdf->page_text(460, 95, '{PAGE_NUM} DARI {PAGE_COUNT}', $font, 9, [17, 24, 39]);
        }
    </script>
</header>

<footer>
    <table class="signoff">
        <tr>
            <th>&nbsp;</th>
            <th>Dibuat Oleh:</th>
            <th>Diperiksa Oleh:</th>
            <th>Disahkan Oleh:</th>
        </tr>
        <tr>
            <td class="row-label">Nama</td>
            <td>{{ $createdBy?->display_name_with_title ?? '-' }}</td>
            <td>{{ $reviewedBy?->display_name_with_title ?? '-' }}</td>
            <td>{{ $approvedBy?->display_name_with_title ?? '-' }}</td>
        </tr>
        <tr>
            <td class="row-label">Tanda Tangan</td>
            <td style="height: 28px">&nbsp;</td>
            <td style="height: 28px">&nbsp;</td>
            <td style="height: 28px">&nbsp;</td>
        </tr>
        <tr>
            <td class="row-label">Jabatan</td>
            <td>{{ $createdBy?->rank ?? '-' }}</td>
            <td>{{ $reviewedBy?->rank ?? '-' }}</td>
            <td>{{ $approvedBy?->rank ?? '-' }}</td>
        </tr>
    </table>

    <div class="notice">{{ $redNotice }}</div>

    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->get_font('DejaVu Sans', 'normal');
            $pdf->page_text(520, 805, '{PAGE_NUM}/{PAGE_COUNT}', $font, 9, [55, 65, 81]);
        }
    </script>
</footer>

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

        <table class="doc-header" style="margin-top: 6px">
            <tr>
                <td style="width: 35%; font-weight: 700; text-align: center;">1. TUJUAN</td>
                <td style="width: 65%;">{!! $renderAnswer('purpose') !!}</td>
            </tr>
            <tr>
                <td style="font-weight: 700; text-align: center;">2. RUANG LINGKUP</td>
                <td>{!! $renderAnswer('scope') !!}</td>
            </tr>
            <tr>
                <td style="font-weight: 700; text-align: center;">3. TANGGUNG JAWAB</td>
                <td>
                    @php
                        $responsibility = $getAnswer('responsibilities');
                    @endphp
                    @if($responsibility === '' || \App\Support\QmhAnswerSanitizer::plainText($responsibility) === '')
                        <p class="qmh-answer">JABATAN LABORATORIUM</p>
                    @else
                        {!! $renderAnswer('responsibilities') !!}
                    @endif
                </td>
            </tr>
            <tr>
                <td style="font-weight: 700; text-align: center;">4. ACUAN</td>
                <td>
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
                </td>
            </tr>
            <tr>
                <td style="font-weight: 700; text-align: center;">5. INSTRUKSI KERJA</td>
                <td>{!! $renderAnswer('instructions') !!}</td>
            </tr>
            <tr>
                <td style="font-weight: 700; text-align: center;">6. DOKUMENTASI YANG DIPERLUKAN</td>
                <td>
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
                </td>
            </tr>
            <tr>
                <td style="font-weight: 700; text-align: center;">7. PENUTUP</td>
                <td>
                    @if($closingValue === '' || \App\Support\QmhAnswerSanitizer::plainText($closingValue) === '')
                        <p class="qmh-answer"><strong>{{ $closingDefault }}</strong></p>
                    @elseif(\App\Support\QmhAnswerSanitizer::looksLikeHtml($closingValue))
                        <div class="qmh-answer"><strong>{!! $closingValue !!}</strong></div>
                    @else
                        <p class="qmh-answer"><strong>{!! nl2br(e($closingValue)) !!}</strong></p>
                    @endif
                </td>
            </tr>
        </table>
    @elseif(count($questions) > 0 && $hasStructuredAnswers)
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
    @else
        {!! $contentHtml ?? ($revision->content_html ?: '<p>Konten dokumen belum tersedia.</p>') !!}
    @endif
</main>

</body>
</html>
