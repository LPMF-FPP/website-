<?php

declare(strict_types=1);

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

final class QmhAnswerSanitizer
{
    /**
     * Keep this small and predictable (DomPDF-friendly).
     *
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
    ];

    /**
     * @return array<string, mixed>
     */
    public static function sanitizeAnswersJson(mixed $answers): array
    {
        if (! is_array($answers)) {
            return [];
        }

        $sanitized = [];
        foreach ($answers as $key => $value) {
            if (! is_string($key) || trim($key) === '') {
                continue;
            }

            $sanitized[$key] = self::sanitizeAnswerValue($value);
        }

        return $sanitized;
    }

    public static function sanitizeAnswerValue(mixed $value): mixed
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return '';
            }

            if (! self::looksLikeHtml($trimmed)) {
                return $trimmed;
            }

            return self::sanitizeRichText($trimmed);
        }

        if (is_array($value)) {
            $items = [];
            foreach ($value as $item) {
                if (! is_string($item)) {
                    continue;
                }

                $trimmed = trim($item);
                if ($trimmed === '') {
                    continue;
                }

                if (! self::looksLikeHtml($trimmed)) {
                    $items[] = $trimmed;

                    continue;
                }

                $sanitized = self::sanitizeRichText($trimmed);
                if ($sanitized !== '') {
                    $items[] = $sanitized;
                }
            }

            return $items;
        }

        return $value;
    }

    public static function looksLikeHtml(string $value): bool
    {
        return (bool) preg_match('/<\/?[a-z][\s\S]*>/i', $value);
    }

    public static function plainText(string $htmlOrText): string
    {
        if (! self::looksLikeHtml($htmlOrText)) {
            return trim(str_replace(["\xc2\xa0"], ' ', $htmlOrText));
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>'.$htmlOrText, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $text = $doc->textContent ?? '';

        return trim(str_replace(["\xc2\xa0"], ' ', (string) $text));
    }

    public static function sanitizeRichText(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $xpath = new DOMXPath($document);

        // Drop scripts/styles and comments outright.
        foreach ($xpath->query('//script | //style | //comment()') as $node) {
            $node->parentNode?->removeChild($node);
        }

        // Unwrap disallowed tags but keep their children.
        $elements = [];
        foreach ($xpath->query('//*') as $node) {
            if ($node instanceof DOMElement) {
                if ($node->hasAttributes() && $node->attributes !== null) {
                    while ($node->attributes->length > 0) {
                        $attr = $node->attributes->item(0);
                        if ($attr === null) {
                            break;
                        }

                        $node->removeAttributeNode($attr);
                    }
                }

                $elements[] = $node;
            }
        }

        $allowed = array_flip(self::ALLOWED_TAGS);
        foreach ($elements as $el) {
            $tag = strtolower($el->tagName);
            if (isset($allowed[$tag])) {
                continue;
            }

            self::unwrapNode($el);
        }

        libxml_clear_errors();

        $result = trim((string) $document->saveHTML());

        return self::plainText($result) === '' ? '' : $result;
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
