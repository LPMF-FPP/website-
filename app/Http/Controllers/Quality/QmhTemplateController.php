<?php

namespace App\Http\Controllers\Quality;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quality\StoreQmhTemplateRequest;
use App\Http\Requests\Quality\UpdateQmhTemplateRequest;
use App\Models\QmhTemplate;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class QmhTemplateController extends Controller
{
    private const DEFAULT_CLAUSE = 4;

    public function index(Request $request)
    {
        $filters = validator($request->only(['doc_type', 'status', 'search']), [
            'doc_type' => ['nullable', 'in:sop,ik,fr'],
            'status' => ['nullable', 'in:active,inactive'],
            'search' => ['nullable', 'string'],
        ])->validate();

        $templates = QmhTemplate::query()
            ->when(isset($filters['doc_type']), fn ($query) => $query->where('doc_type', $filters['doc_type']))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when(isset($filters['search']), function ($query) use ($filters) {
                $query->where('name', 'like', '%'.$filters['search'].'%');
            })
            ->orderBy('doc_type')
            ->orderByDesc('version')
            ->paginate(20)
            ->appends($request->query());

        return view('quality.templates.index', [
            'templates' => $templates,
        ]);
    }

    public function store(StoreQmhTemplateRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $uploadedFile = $request->file('file');
        $disk = 'local';
        $folder = sprintf('qmh/templates/%s', $validated['doc_type']);
        $path = $uploadedFile->store($folder, $disk);

        DB::transaction(function () use ($validated, $request, $path, $disk): void {
            $nextVersion = (int) QmhTemplate::query()
                ->where('doc_type', $validated['doc_type'])
                ->max('version') + 1;

            QmhTemplate::query()
                ->where('doc_type', $validated['doc_type'])
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $contentHtml = $this->extractContentHtmlFromDocx($disk, $path);

            $template = QmhTemplate::query()->create([
                'name' => $validated['name'],
                'clause' => self::DEFAULT_CLAUSE,
                'doc_type' => $validated['doc_type'],
                'version' => $nextVersion,
                'storage_disk' => $disk,
                'source_docx_path' => $path,
                'is_active' => true,
                'metadata' => [
                    'version_notes' => $validated['version_notes'] ?? null,
                    'uploaded_by' => $request->user()?->id,
                    'content_html' => $contentHtml,
                    'form_schema' => $validated['form_schema'] ?? null,
                ],
            ]);

            Audit::log('QMH_TEMPLATE_UPLOAD', (string) $template->id, null, [
                'clause' => $template->clause,
                'doc_type' => $template->doc_type,
                'version' => $template->version,
                'path' => $template->source_docx_path,
            ]);
        });

        return redirect()
            ->route('quality.templates.index')
            ->with('success', 'Template QMH berhasil diunggah dan diaktifkan.');
    }

    public function edit(Request $request, QmhTemplate $template): View
    {
        $this->authorizeTemplateManage($request);

        return view('quality.templates.edit', [
            'template' => $template,
        ]);
    }

    public function update(UpdateQmhTemplateRequest $request, QmhTemplate $template): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $template, $validated): void {
            $before = [
                'name' => $template->name,
                'doc_type' => $template->doc_type,
                'source_docx_path' => $template->source_docx_path,
                'metadata' => $template->metadata,
            ];

            $nextPath = $template->source_docx_path;
            if ($request->hasFile('file')) {
                $uploadedFile = $request->file('file');
                $disk = $template->storage_disk;
                $folder = sprintf('qmh/templates/%s', $validated['doc_type']);
                $nextPath = $uploadedFile->store($folder, $disk);

                $extractedHtml = $this->extractContentHtmlFromDocx($disk, $nextPath);
                if ($extractedHtml !== null && ! isset($validated['content_html'])) {
                    $validated['content_html'] = $extractedHtml;
                }
            }

            $nextMetadata = is_array($template->metadata) ? $template->metadata : [];
            $nextMetadata['version_notes'] = $validated['version_notes'] ?? null;
            $nextMetadata['updated_by'] = $request->user()?->id;
            $nextMetadata['content_html'] = $validated['content_html'] ?? ($nextMetadata['content_html'] ?? null);

            if (array_key_exists('form_schema', $validated)) {
                $nextMetadata['form_schema'] = $validated['form_schema'];
            }

            $template->forceFill([
                'name' => $validated['name'],
                'doc_type' => $validated['doc_type'],
                'source_docx_path' => $nextPath,
                'metadata' => $nextMetadata,
            ])->save();

            if ($template->is_active) {
                QmhTemplate::query()
                    ->where('doc_type', $template->doc_type)
                    ->where('id', '!=', $template->id)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }

            Audit::log('QMH_TEMPLATE_UPDATE', (string) $template->id, $before, [
                'name' => $template->name,
                'doc_type' => $template->doc_type,
                'source_docx_path' => $template->source_docx_path,
                'metadata' => $template->metadata,
            ]);
        });

        return redirect()
            ->route('quality.templates.index')
            ->with('success', 'Template berhasil diperbarui.');
    }

    public function activate(QmhTemplate $template): RedirectResponse
    {
        DB::transaction(function () use ($template): void {
            QmhTemplate::query()
                ->where('doc_type', $template->doc_type)
                ->where('id', '!=', $template->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $before = $template->is_active;

            $template->forceFill(['is_active' => true])->save();

            Audit::log('QMH_TEMPLATE_ACTIVATE', (string) $template->id, [
                'is_active' => $before,
            ], [
                'is_active' => true,
                'clause' => $template->clause,
                'doc_type' => $template->doc_type,
                'version' => $template->version,
            ]);
        });

        return redirect()
            ->route('quality.templates.index')
            ->with('success', 'Template berhasil diaktifkan.');
    }

    public function deactivate(QmhTemplate $template): RedirectResponse
    {
        $before = $template->is_active;

        $template->forceFill(['is_active' => false])->save();

        Audit::log('QMH_TEMPLATE_DEACTIVATE', (string) $template->id, [
            'is_active' => $before,
        ], [
            'is_active' => false,
            'clause' => $template->clause,
            'doc_type' => $template->doc_type,
            'version' => $template->version,
        ]);

        return redirect()
            ->route('quality.templates.index')
            ->with('success', 'Template berhasil dinonaktifkan.');
    }

    public function preview(Request $request, QmhTemplate $template): View
    {
        $this->authorizePreviewAccess($request);
        $this->assertTemplateFileExists($template);

        $previewFileUrl = URL::temporarySignedRoute(
            'quality.templates.preview.file',
            now()->addMinutes(10),
            ['template' => $template->id]
        );

        return view('quality.templates.preview', [
            'template' => $template,
            'previewFileUrl' => $previewFileUrl,
            'officeViewerUrl' => 'https://view.officeapps.live.com/op/embed.aspx?src='.rawurlencode($previewFileUrl),
        ]);
    }

    public function previewFile(QmhTemplate $template): StreamedResponse
    {
        $this->assertTemplateFileExists($template);

        return Storage::disk($template->storage_disk)->response(
            $template->source_docx_path,
            $this->buildTemplateFileName($template),
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
            'inline'
        );
    }

    private function authorizePreviewAccess(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user !== null && (
                $user->hasPermission('qmh.create')
                || $user->hasPermission('qmh.template.manage')
            ),
            403,
            'Anda tidak memiliki akses preview template.'
        );
    }

    private function authorizeTemplateManage(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user !== null && $user->hasPermission('qmh.template.manage'),
            403,
            'Anda tidak memiliki akses untuk mengelola template.'
        );
    }

    private function assertTemplateFileExists(QmhTemplate $template): void
    {
        abort_if(
            $template->source_docx_path === null
            || ! Storage::disk($template->storage_disk)->exists($template->source_docx_path),
            404,
            'File template tidak ditemukan.'
        );
    }

    private function buildTemplateFileName(QmhTemplate $template): string
    {
        return sprintf(
            '%s-v%d.docx',
            Str::slug($template->name ?: $template->doc_type.'-template'),
            (int) $template->version
        );
    }

    private function extractContentHtmlFromDocx(string $disk, string $path): ?string
    {
        if (! Storage::disk($disk)->exists($path)) {
            return null;
        }

        $absolutePath = Storage::disk($disk)->path($path);
        $zip = new ZipArchive;
        if ($zip->open($absolutePath) !== true) {
            return null;
        }

        $documentXml = $zip->getFromName('word/document.xml');
        if (! is_string($documentXml) || trim($documentXml) === '') {
            $zip->close();

            return null;
        }

        $relationships = $this->extractDocxRelationships($zip);
        $numberingDefinitions = $this->extractDocxNumberingDefinitions($zip);
        $contentHtml = $this->convertDocxXmlToHtml($documentXml, $zip, $relationships, $numberingDefinitions);
        $footerHtml = $this->extractDocxFooterHtml($documentXml, $zip, $relationships, $numberingDefinitions);

        $zip->close();

        $combinedHtml = trim($contentHtml.$footerHtml);

        return $combinedHtml !== '' ? $combinedHtml : '<p></p>';
    }

    /**
     * @return array<string, string>
     */
    private function extractDocxRelationships(ZipArchive $zip): array
    {
        $relsXml = $zip->getFromName('word/_rels/document.xml.rels');
        if (! is_string($relsXml) || trim($relsXml) === '') {
            return [];
        }

        $dom = new \DOMDocument;
        if (! @($dom->loadXML($relsXml))) {
            return [];
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('rel', 'http://schemas.openxmlformats.org/package/2006/relationships');

        $relationships = [];
        foreach ($xpath->query('//rel:Relationship') ?: [] as $relationshipNode) {
            if (! ($relationshipNode instanceof \DOMElement)) {
                continue;
            }

            $id = trim($relationshipNode->getAttribute('Id'));
            $target = trim($relationshipNode->getAttribute('Target'));
            if ($id === '' || $target === '') {
                continue;
            }

            $relationships[$id] = $target;
        }

        return $relationships;
    }

    /**
     * @return array<string, array<int, array{format: string, text: string, start: int}>>
     */
    private function extractDocxNumberingDefinitions(ZipArchive $zip): array
    {
        $numberingXml = $zip->getFromName('word/numbering.xml');
        if (! is_string($numberingXml) || trim($numberingXml) === '') {
            return [];
        }

        $dom = new \DOMDocument;
        if (! @($dom->loadXML($numberingXml))) {
            return [];
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $abstractLevels = [];
        foreach ($xpath->query('//w:abstractNum') ?: [] as $abstractNode) {
            if (! ($abstractNode instanceof \DOMElement)) {
                continue;
            }

            $abstractId = trim($abstractNode->getAttribute('w:abstractNumId'));
            if ($abstractId === '') {
                continue;
            }

            $levels = [];
            foreach ($xpath->query('./w:lvl', $abstractNode) ?: [] as $levelNode) {
                if (! ($levelNode instanceof \DOMElement)) {
                    continue;
                }

                $ilvl = (int) $levelNode->getAttribute('w:ilvl');
                $format = trim((string) $xpath->evaluate('string(./w:numFmt/@w:val)', $levelNode));
                $text = trim((string) $xpath->evaluate('string(./w:lvlText/@w:val)', $levelNode));
                $start = (int) $xpath->evaluate('string(./w:start/@w:val)', $levelNode);

                $levels[$ilvl] = [
                    'format' => $format !== '' ? $format : 'decimal',
                    'text' => $text,
                    'start' => $start > 0 ? $start : 1,
                ];
            }

            $abstractLevels[$abstractId] = $levels;
        }

        $definitions = [];
        foreach ($xpath->query('//w:num') ?: [] as $numNode) {
            if (! ($numNode instanceof \DOMElement)) {
                continue;
            }

            $numId = trim($numNode->getAttribute('w:numId'));
            $abstractId = trim((string) $xpath->evaluate('string(./w:abstractNumId/@w:val)', $numNode));

            if ($numId === '' || $abstractId === '' || ! array_key_exists($abstractId, $abstractLevels)) {
                continue;
            }

            $definitions[$numId] = $abstractLevels[$abstractId];
        }

        return $definitions;
    }

    /**
     * @param  array<string, string>  $relationships
     * @param  array<string, array<int, array{format: string, text: string, start: int}>>  $numberingDefinitions
     */
    private function convertDocxXmlToHtml(string $documentXml, ZipArchive $zip, array $relationships, array $numberingDefinitions): string
    {
        $dom = new \DOMDocument;
        if (! @($dom->loadXML($documentXml))) {
            return '<p></p>';
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
        $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

        $numberingState = [];
        $chunks = [];
        foreach ($xpath->query('//w:body/*') ?: [] as $blockNode) {
            if (! ($blockNode instanceof \DOMElement)) {
                continue;
            }

            if ($blockNode->localName === 'sectPr') {
                continue;
            }

            if ($blockNode->localName === 'p') {
                $chunks[] = $this->renderDocxParagraphNode($blockNode, $xpath, $zip, $relationships, $numberingDefinitions, $numberingState);

                continue;
            }

            if ($blockNode->localName === 'tbl') {
                $chunks[] = $this->renderDocxTableNode($blockNode, $xpath, $zip, $relationships, $numberingDefinitions, $numberingState);
            }
        }

        $contentHtml = trim(implode('', array_filter($chunks)));
        if ($contentHtml === '') {
            return '<p></p>';
        }

        return $contentHtml;
    }

    /**
     * @param  array<string, string>  $relationships
     * @param  array<string, array<int, array{format: string, text: string, start: int}>>  $numberingDefinitions
     */
    private function extractDocxFooterHtml(string $documentXml, ZipArchive $zip, array $relationships, array $numberingDefinitions): string
    {
        $dom = new \DOMDocument;
        if (! @($dom->loadXML($documentXml))) {
            return '';
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

        $defaultFooterTargets = [];
        $fallbackFooterTargets = [];
        foreach ($xpath->query('//w:sectPr/w:footerReference') ?: [] as $footerReference) {
            if (! ($footerReference instanceof \DOMElement)) {
                continue;
            }

            $relationshipId = trim($footerReference->getAttribute('r:id'));
            if ($relationshipId === '' || ! array_key_exists($relationshipId, $relationships)) {
                continue;
            }

            $target = $this->resolveDocxTargetPath($relationships[$relationshipId]);
            if ($target === '') {
                continue;
            }

            $referenceType = strtolower(trim($footerReference->getAttribute('w:type')));
            if ($referenceType === '' || $referenceType === 'default') {
                $defaultFooterTargets[] = $target;

                continue;
            }

            $fallbackFooterTargets[] = $target;
        }

        $footerTargets = $defaultFooterTargets !== []
            ? array_values(array_unique($defaultFooterTargets))
            : array_values(array_unique($fallbackFooterTargets));

        if ($footerTargets === []) {
            return '';
        }

        $chunks = [];
        $numberingState = [];
        foreach ($footerTargets as $footerTarget) {
            $footerXml = $zip->getFromName($footerTarget);
            if (! is_string($footerXml) || trim($footerXml) === '') {
                continue;
            }

            $footerDom = new \DOMDocument;
            if (! @($footerDom->loadXML($footerXml))) {
                continue;
            }

            $footerXpath = new \DOMXPath($footerDom);
            $footerXpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $footerXpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
            $footerXpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

            foreach ($footerXpath->query('/w:ftr/*') ?: [] as $blockNode) {
                if (! ($blockNode instanceof \DOMElement)) {
                    continue;
                }

                if ($blockNode->localName === 'p') {
                    $html = $this->renderDocxParagraphNode($blockNode, $footerXpath, $zip, $relationships, $numberingDefinitions, $numberingState);
                    if ($this->isMeaningfulHtmlContent($html)) {
                        $chunks[] = $html;
                    }

                    continue;
                }

                if ($blockNode->localName === 'tbl') {
                    $html = $this->renderDocxTableNode($blockNode, $footerXpath, $zip, $relationships, $numberingDefinitions, $numberingState);
                    if ($this->isMeaningfulHtmlContent($html)) {
                        $chunks[] = $html;
                    }
                }
            }
        }

        return implode('', $chunks);
    }

    private function isMeaningfulHtmlContent(string $html): bool
    {
        $plainText = trim(str_replace(["\xc2\xa0", '&nbsp;'], '', strip_tags($html)));

        return $plainText !== '';
    }

    /**
     * @param  array<string, string>  $relationships
     * @param  array<string, array<int, array{format: string, text: string, start: int}>>  $numberingDefinitions
     * @param  array<string, int>  $numberingState
     */
    private function renderDocxParagraphNode(\DOMElement $paragraphNode, \DOMXPath $xpath, ZipArchive $zip, array $relationships, array $numberingDefinitions, array &$numberingState): string
    {
        $styleName = strtolower((string) $xpath->evaluate('string(./w:pPr/w:pStyle/@w:val)', $paragraphNode));
        $tagName = match (true) {
            str_starts_with($styleName, 'heading1') || $styleName === 'title' => 'h1',
            str_starts_with($styleName, 'heading2') => 'h2',
            str_starts_with($styleName, 'heading3') => 'h3',
            default => 'p',
        };

        $prefix = $this->resolveDocxParagraphNumberingPrefix($paragraphNode, $xpath, $numberingDefinitions, $numberingState);
        $content = $this->renderDocxInlineNodes($paragraphNode, $xpath, $zip, $relationships);

        $hasPageField = ((int) $xpath->evaluate('count(.//w:instrText[contains(translate(normalize-space(.), "abcdefghijklmnopqrstuvwxyz", "ABCDEFGHIJKLMNOPQRSTUVWXYZ"), "PAGE")])', $paragraphNode)) > 0;
        if ($hasPageField) {
            $plainPageText = preg_replace('/\s+/', '', html_entity_decode(strip_tags($content)));
            if (is_string($plainPageText) && preg_match('/\/(\d+)/', $plainPageText, $matches) === 1) {
                $content = '1/'.$matches[1];
            } else {
                $content = '1';
            }
        }

        $content = $prefix.$content;

        $paragraphAlignment = strtolower(trim((string) $xpath->evaluate('string(./w:pPr/w:jc/@w:val)', $paragraphNode)));
        $normalizedAlignment = match ($paragraphAlignment) {
            'left', 'start' => 'left',
            'center' => 'center',
            'right', 'end' => 'right',
            'both', 'distribute', 'justified' => 'justify',
            default => '',
        };

        $attributes = $normalizedAlignment !== ''
            ? ' style="text-align: '.e($normalizedAlignment).';"'
            : '';

        return sprintf('<%1$s%3$s>%2$s</%1$s>', $tagName, $content, $attributes);
    }

    /**
     * @param  array<string, string>  $relationships
     * @param  array<string, array<int, array{format: string, text: string, start: int}>>  $numberingDefinitions
     * @param  array<string, int>  $numberingState
     */
    private function renderDocxTableNode(\DOMElement $tableNode, \DOMXPath $xpath, ZipArchive $zip, array $relationships, array $numberingDefinitions, array &$numberingState): string
    {
        $rows = [];
        foreach ($xpath->query('./w:tr', $tableNode) ?: [] as $rowNode) {
            if (! ($rowNode instanceof \DOMElement)) {
                continue;
            }

            $cells = [];
            foreach ($xpath->query('./w:tc', $rowNode) ?: [] as $cellNode) {
                if (! ($cellNode instanceof \DOMElement)) {
                    continue;
                }

                $cellContent = $this->renderDocxTableCellNode($cellNode, $xpath, $zip, $relationships, $numberingDefinitions, $numberingState);
                $cells[] = '<td>'.$cellContent.'</td>';
            }

            $rows[] = '<tr>'.implode('', $cells).'</tr>';
        }

        return '<table><tbody>'.implode('', $rows).'</tbody></table>';
    }

    /**
     * @param  array<string, string>  $relationships
     * @param  array<string, array<int, array{format: string, text: string, start: int}>>  $numberingDefinitions
     * @param  array<string, int>  $numberingState
     */
    private function renderDocxTableCellNode(\DOMElement $cellNode, \DOMXPath $xpath, ZipArchive $zip, array $relationships, array $numberingDefinitions, array &$numberingState): string
    {
        $chunks = [];
        foreach ($xpath->query('./w:p | ./w:tbl', $cellNode) ?: [] as $blockNode) {
            if (! ($blockNode instanceof \DOMElement)) {
                continue;
            }

            if ($blockNode->localName === 'p') {
                $chunks[] = $this->renderDocxParagraphNode($blockNode, $xpath, $zip, $relationships, $numberingDefinitions, $numberingState);

                continue;
            }

            if ($blockNode->localName === 'tbl') {
                $chunks[] = $this->renderDocxTableNode($blockNode, $xpath, $zip, $relationships, $numberingDefinitions, $numberingState);
            }
        }

        return implode('', $chunks);
    }

    /**
     * @param  array<string, string>  $relationships
     */
    private function renderDocxInlineNodes(\DOMElement $contextNode, \DOMXPath $xpath, ZipArchive $zip, array $relationships): string
    {
        $parts = [];
        foreach ($xpath->query('.//w:r', $contextNode) ?: [] as $runNode) {
            if (! ($runNode instanceof \DOMElement)) {
                continue;
            }

            $runHtml = $this->renderDocxRunNode($runNode, $xpath, $zip, $relationships);
            if ($runHtml !== '') {
                $parts[] = $runHtml;
            }
        }

        return implode('', $parts);
    }

    /**
     * @param  array<string, string>  $relationships
     */
    private function renderDocxRunNode(\DOMElement $runNode, \DOMXPath $xpath, ZipArchive $zip, array $relationships): string
    {
        $parts = [];
        foreach ($xpath->query('./w:t | ./w:br | ./w:tab | ./w:drawing', $runNode) ?: [] as $inlineNode) {
            if (! ($inlineNode instanceof \DOMElement)) {
                continue;
            }

            if ($inlineNode->localName === 't') {
                $parts[] = e($inlineNode->textContent);

                continue;
            }

            if ($inlineNode->localName === 'br') {
                $parts[] = '<br>';

                continue;
            }

            if ($inlineNode->localName === 'tab') {
                $parts[] = '&emsp;';

                continue;
            }

            if ($inlineNode->localName === 'drawing') {
                $imageHtml = $this->renderDocxImageNode($inlineNode, $xpath, $zip, $relationships);
                if ($imageHtml !== null) {
                    $parts[] = $imageHtml;
                }
            }
        }

        $content = implode('', $parts);
        if ($content === '') {
            return '';
        }

        $isBold = ((int) $xpath->evaluate('count(./w:rPr/w:b)', $runNode)) > 0
            && $this->isDocxToggleEnabled((string) $xpath->evaluate('string(./w:rPr/w:b/@w:val)', $runNode));
        $isItalic = ((int) $xpath->evaluate('count(./w:rPr/w:i)', $runNode)) > 0
            && $this->isDocxToggleEnabled((string) $xpath->evaluate('string(./w:rPr/w:i/@w:val)', $runNode));
        $isUnderline = ((int) $xpath->evaluate('count(./w:rPr/w:u)', $runNode)) > 0
            && $this->isDocxToggleEnabled((string) $xpath->evaluate('string(./w:rPr/w:u/@w:val)', $runNode), ['none']);

        $color = $this->normalizeDocxColorValue((string) $xpath->evaluate('string(./w:rPr/w:color/@w:val)', $runNode));

        if ($isBold) {
            $content = '<strong>'.$content.'</strong>';
        }

        if ($isItalic) {
            $content = '<em>'.$content.'</em>';
        }

        if ($isUnderline) {
            $content = '<u>'.$content.'</u>';
        }

        if ($color !== null) {
            $content = '<span style="color:#'.$color.'">'.$content.'</span>';
        }

        return $content;
    }

    /**
     * @param  list<string>  $additionalDisabledValues
     */
    private function isDocxToggleEnabled(string $rawValue, array $additionalDisabledValues = []): bool
    {
        $value = strtolower(trim($rawValue));
        if ($value === '') {
            return true;
        }

        $disabledValues = array_merge(['0', 'false', 'off'], $additionalDisabledValues);

        return ! in_array($value, $disabledValues, true);
    }

    private function normalizeDocxColorValue(string $rawValue): ?string
    {
        $value = strtoupper(trim($rawValue));
        if ($value === '' || $value === 'AUTO') {
            return null;
        }

        $value = preg_replace('/[^0-9A-F]/', '', $value) ?? '';
        if (strlen($value) < 6) {
            return null;
        }

        return substr($value, 0, 6);
    }

    /**
     * @param  array<string, array<int, array{format: string, text: string, start: int}>>  $numberingDefinitions
     * @param  array<string, int>  $numberingState
     */
    private function resolveDocxParagraphNumberingPrefix(\DOMElement $paragraphNode, \DOMXPath $xpath, array $numberingDefinitions, array &$numberingState): string
    {
        $numId = trim((string) $xpath->evaluate('string(./w:pPr/w:numPr/w:numId/@w:val)', $paragraphNode));
        if ($numId === '' || ! array_key_exists($numId, $numberingDefinitions)) {
            return '';
        }

        $ilvl = (int) $xpath->evaluate('string(./w:pPr/w:numPr/w:ilvl/@w:val)', $paragraphNode);
        $levelDefinition = $numberingDefinitions[$numId][$ilvl]
            ?? $numberingDefinitions[$numId][0]
            ?? null;

        if ($levelDefinition === null) {
            return '';
        }

        $counterKey = $numId.':'.$ilvl;
        $startValue = max((int) ($levelDefinition['start'] ?? 1), 1);
        if (! array_key_exists($counterKey, $numberingState)) {
            $numberingState[$counterKey] = $startValue;
        } else {
            $numberingState[$counterKey]++;
        }

        $marker = $this->formatDocxNumberingMarker($numberingState[$counterKey], $levelDefinition['format'] ?? 'decimal', $levelDefinition['text'] ?? '');

        return $marker !== '' ? e($marker).' ' : '';
    }

    private function formatDocxNumberingMarker(int $value, string $format, string $levelText): string
    {
        $normalizedFormat = strtolower(trim($format));
        $template = trim($levelText);

        if ($normalizedFormat === 'bullet') {
            return '•';
        }

        $formattedNumber = $this->formatDocxListNumber($value, $normalizedFormat);
        if ($template === '') {
            return $formattedNumber.'.';
        }

        return (string) preg_replace('/%\d+/', $formattedNumber, $template);
    }

    private function formatDocxListNumber(int $value, string $format): string
    {
        return match ($format) {
            'lowerletter' => $this->toAlphabetic($value, false),
            'upperletter' => $this->toAlphabetic($value, true),
            'lowerroman' => strtolower($this->toRoman($value)),
            'upperroman' => $this->toRoman($value),
            default => (string) $value,
        };
    }

    private function toAlphabetic(int $value, bool $upper): string
    {
        if ($value < 1) {
            return '';
        }

        $result = '';
        $number = $value;
        while ($number > 0) {
            $number--;
            $result = chr(($number % 26) + 97).$result;
            $number = intdiv($number, 26);
        }

        return $upper ? strtoupper($result) : $result;
    }

    private function toRoman(int $value): string
    {
        if ($value < 1) {
            return '';
        }

        $map = [
            'M' => 1000,
            'CM' => 900,
            'D' => 500,
            'CD' => 400,
            'C' => 100,
            'XC' => 90,
            'L' => 50,
            'XL' => 40,
            'X' => 10,
            'IX' => 9,
            'V' => 5,
            'IV' => 4,
            'I' => 1,
        ];

        $result = '';
        $number = $value;
        foreach ($map as $roman => $intValue) {
            while ($number >= $intValue) {
                $result .= $roman;
                $number -= $intValue;
            }
        }

        return $result;
    }

    /**
     * @param  array<string, string>  $relationships
     */
    private function renderDocxImageNode(\DOMElement $drawingNode, \DOMXPath $xpath, ZipArchive $zip, array $relationships): ?string
    {
        $relationshipId = trim((string) $xpath->evaluate('string(.//a:blip/@r:embed)', $drawingNode));
        if ($relationshipId === '' || ! array_key_exists($relationshipId, $relationships)) {
            return null;
        }

        $entryPath = $this->resolveDocxTargetPath($relationships[$relationshipId]);
        if ($entryPath === '') {
            return null;
        }

        $binary = $zip->getFromName($entryPath);
        if (! is_string($binary) || $binary === '') {
            return null;
        }

        $mimeType = $this->guessDocxAssetMimeType($entryPath);

        return sprintf('<img src="data:%s;base64,%s" alt="">', $mimeType, base64_encode($binary));
    }

    private function resolveDocxTargetPath(string $target): string
    {
        $target = str_replace('\\\\', '/', trim($target));
        if ($target === '') {
            return '';
        }

        $fullPath = str_starts_with($target, '/')
            ? ltrim($target, '/')
            : 'word/'.$target;

        $parts = [];
        foreach (explode('/', $fullPath) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                array_pop($parts);

                continue;
            }

            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    private function guessDocxAssetMimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'bmp' => 'image/bmp',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };
    }
}
