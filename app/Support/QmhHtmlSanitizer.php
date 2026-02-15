<?php

declare(strict_types=1);

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

final class QmhHtmlSanitizer
{
    /**
     * QMH HTML is user-controlled (templates + editor). Keep it predictable and safe.
     *
     * Notes:
     * - Removes scripts/styles/event handlers.
     * - Unwraps disallowed tags but keeps text.
     * - Drops remote URLs in src/href to avoid data exfil/SSRF.
     */

    /**
     * @var array<int, string>
     */
    private const ALLOWED_TAGS = [
        'p',
        'br',
        'strong',
        'b',
        'em',
        'i',
        'u',
        'ul',
        'ol',
        'li',
        'h1',
        'h2',
        'h3',
        'hr',
        'blockquote',
        'pre',
        'code',
        'sup',
        'sub',
        'div',
        'span',
        'table',
        'thead',
        'tbody',
        'tfoot',
        'tr',
        'th',
        'td',
        'img',
        'a',
    ];

    public static function sanitize(?string $html): string
    {
        $raw = is_string($html) ? trim($html) : '';
        if ($raw === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$raw, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $xpath = new DOMXPath($document);

        foreach ($xpath->query('//script | //style | //comment()') ?: [] as $node) {
            $node->parentNode?->removeChild($node);
        }

        $allowed = array_flip(self::ALLOWED_TAGS);

        /** @var array<int, DOMElement> $elements */
        $elements = [];
        foreach ($xpath->query('//*') ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }

        foreach ($elements as $el) {
            $tag = strtolower($el->tagName);

            if (! isset($allowed[$tag])) {
                self::unwrapNode($el);

                continue;
            }

            self::sanitizeAttributes($el);
        }

        libxml_clear_errors();

        $result = trim((string) $document->saveHTML());

        $result = preg_replace('/^<\?xml[^>]+>\s*/i', '', $result) ?? $result;
        $result = trim($result);

        return self::plainText($result) === '' ? '' : $result;
    }

    public static function plainText(string $htmlOrText): string
    {
        if (! QmhAnswerSanitizer::looksLikeHtml($htmlOrText)) {
            return trim(str_replace(["\xc2\xa0"], ' ', $htmlOrText));
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>'.$htmlOrText, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $text = $doc->textContent ?? '';

        return trim(str_replace(["\xc2\xa0"], ' ', (string) $text));
    }

    private static function sanitizeAttributes(DOMElement $el): void
    {
        $tag = strtolower($el->tagName);

        $allowedAttrs = match ($tag) {
            'td', 'th' => ['colspan', 'rowspan', 'style'],
            'img' => ['src', 'alt', 'width', 'height', 'style'],
            'a' => ['href', 'target', 'rel'],
            default => ['style'],
        };

        $allowedAttrs = array_flip($allowedAttrs);

        if ($el->hasAttributes() && $el->attributes !== null) {
            $toRemove = [];
            foreach ($el->attributes as $attr) {
                $name = strtolower((string) $attr->name);

                if (str_starts_with($name, 'on')) {
                    $toRemove[] = $attr->name;

                    continue;
                }

                if (! isset($allowedAttrs[$name])) {
                    $toRemove[] = $attr->name;
                }
            }

            foreach ($toRemove as $name) {
                $el->removeAttribute($name);
            }
        }

        if ($el->hasAttribute('style')) {
            $sanitized = self::sanitizeStyle($el->getAttribute('style'));
            if ($sanitized === '') {
                $el->removeAttribute('style');
            } else {
                $el->setAttribute('style', $sanitized);
            }
        }

        if ($tag === 'img' && $el->hasAttribute('src')) {
            $src = self::sanitizeUrl($el->getAttribute('src'), allowData: true);
            if ($src === null) {
                $el->removeAttribute('src');
            } else {
                $el->setAttribute('src', $src);
            }
        }

        if ($tag === 'a' && $el->hasAttribute('href')) {
            $href = self::sanitizeUrl($el->getAttribute('href'), allowData: false);
            if ($href === null) {
                $el->removeAttribute('href');
                $el->removeAttribute('target');
                $el->removeAttribute('rel');
            } else {
                $el->setAttribute('href', $href);
                if ($el->hasAttribute('target')) {
                    $target = strtolower(trim($el->getAttribute('target')));
                    if ($target === '_blank') {
                        $el->setAttribute('rel', 'noopener');
                    }
                }
            }
        }

        if (($tag === 'td' || $tag === 'th') && $el->hasAttribute('colspan')) {
            $col = preg_replace('/[^0-9]/', '', $el->getAttribute('colspan')) ?? '';
            if ($col === '') {
                $el->removeAttribute('colspan');
            } else {
                $el->setAttribute('colspan', $col);
            }
        }

        if (($tag === 'td' || $tag === 'th') && $el->hasAttribute('rowspan')) {
            $row = preg_replace('/[^0-9]/', '', $el->getAttribute('rowspan')) ?? '';
            if ($row === '') {
                $el->removeAttribute('rowspan');
            } else {
                $el->setAttribute('rowspan', $row);
            }
        }
    }

    private static function sanitizeStyle(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        if (preg_match('/expression\s*\(|url\s*\(/i', $raw) === 1) {
            // Drop entire style attribute if it tries to reference remote content.
            return '';
        }

        $allowedProps = [
            'text-align' => true,
            'color' => true,
        ];

        $kept = [];
        foreach (explode(';', $raw) as $decl) {
            $decl = trim($decl);
            if ($decl === '' || ! str_contains($decl, ':')) {
                continue;
            }

            [$prop, $val] = array_map('trim', explode(':', $decl, 2));
            $prop = strtolower($prop);
            if (! isset($allowedProps[$prop])) {
                continue;
            }

            if ($prop === 'text-align') {
                $v = strtolower($val);
                if (! in_array($v, ['left', 'right', 'center', 'justify'], true)) {
                    continue;
                }
                $kept[] = $prop.': '.$v;

                continue;
            }

            if ($prop === 'color') {
                $v = strtoupper($val);
                $v = preg_replace('/\s+/', '', $v) ?? '';
                if (preg_match('/^#[0-9A-F]{3}([0-9A-F]{3})?$/', $v) !== 1) {
                    continue;
                }
                $kept[] = $prop.': '.$v;
            }
        }

        return implode('; ', $kept);
    }

    private static function sanitizeUrl(string $value, bool $allowData): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $lower = strtolower($value);
        if (str_starts_with($lower, 'javascript:')) {
            return null;
        }

        if ($allowData && str_starts_with($lower, 'data:')) {
            return $value;
        }

        // Allow internal fragments / relative paths.
        if (str_starts_with($value, '#') || str_starts_with($value, '/') || str_starts_with($value, '?')) {
            return $value;
        }

        // Block remote by default to avoid exfil/SSRF.
        if (str_starts_with($lower, 'http://') || str_starts_with($lower, 'https://') || str_starts_with($lower, '//')) {
            return null;
        }

        return null;
    }

    private static function unwrapNode(DOMNode $node): void
    {
        $parent = $node->parentNode;
        if ($parent === null) {
            return;
        }

        while ($node->firstChild) {
            $parent->insertBefore($node->firstChild, $node);
        }

        $parent->removeChild($node);
    }
}
