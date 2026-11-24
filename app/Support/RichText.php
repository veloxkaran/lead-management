<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use Mews\Purifier\Facades\Purifier;

/**
 * One pipeline for the rich-text descriptions on requirements
 * (`requirement`) and support tickets (`details`), so both look the same
 * when viewed as they did when typed.
 *
 * Older rows — and anything from a plain <textarea> such as the client
 * portal or the lead page's quick form — are plain text. Purifier and
 * Quill both collapse bare newlines, so plain text is first turned into
 * escaped paragraphs (blank line) and line breaks (single newline).
 *
 * Quill 2 also writes every list as <ol> and marks bullets with
 * <li data-list="bullet">; Purifier strips data-* attributes, which would
 * turn bulleted lists into numbered ones, so those become real <ul> first.
 */
class RichText
{
    /**
     * Clean HTML for storing — call on input before saving.
     */
    public static function sanitize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (self::isBlank($value)) {
            return '';
        }

        return Purifier::clean(self::normalizeQuillLists(self::toHtml($value)));
    }

    /**
     * Nothing a reader would see: empty, or only empty editor lines
     * (<p><br></p>), spaces and &nbsp;. An image or table counts as content.
     */
    public static function isBlank(?string $value): bool
    {
        $value = (string) $value;

        if (preg_match('/<\s*(img|table|hr)\b/i', $value)) {
            return false;
        }

        $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return preg_replace('/[\s\x{00A0}\x{200B}]+/u', '', $text) === '';
    }

    /**
     * Clean HTML for display (re-cleaned on read so legacy rows are safe).
     */
    public static function render(?string $value): string
    {
        return self::sanitize($value) ?? '';
    }

    /**
     * Plain text becomes <p>/<br> HTML; HTML passes through unchanged.
     */
    public static function toHtml(?string $value): string
    {
        $value = (string) $value;

        if (! self::isPlainText($value)) {
            return $value;
        }

        $text = str_replace(["\r\n", "\r"], "\n", trim($value));

        if ($text === '') {
            return '';
        }

        $paragraphs = preg_split('/\n{2,}/', $text);

        return implode('', array_map(
            fn (string $p) => '<p>'.str_replace("\n", '<br>', e($p)).'</p>',
            $paragraphs
        ));
    }

    /**
     * Splits each Quill <ol> into consecutive runs of the same list type
     * (Quill keeps a bullet list straight after a numbered one in one <ol>)
     * and makes the bullet runs <ul>.
     */
    public static function normalizeQuillLists(string $html): string
    {
        if (stripos($html, 'data-list=') === false) {
            return $html;
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $lists = iterator_to_array($document->getElementsByTagName('ol'));

        foreach ($lists as $ol) {
            $runs = [];

            foreach (iterator_to_array($ol->childNodes) as $child) {
                if (! $child instanceof DOMElement || strtolower($child->nodeName) !== 'li') {
                    continue;
                }

                $tag = $child->getAttribute('data-list') === 'ordered' ? 'ol' : 'ul';

                if ($runs === [] || end($runs)['tag'] !== $tag) {
                    $runs[] = ['tag' => $tag, 'items' => []];
                }

                $runs[array_key_last($runs)]['items'][] = $child;
            }

            if ($runs === []) {
                continue;
            }

            foreach ($runs as $run) {
                $list = $document->createElement($run['tag']);

                foreach ($run['items'] as $item) {
                    $item->removeAttribute('data-list');
                    $list->appendChild($item);
                }

                $ol->parentNode->insertBefore($list, $ol);
            }

            $ol->parentNode->removeChild($ol);
        }

        $body = $document->getElementsByTagName('body')->item(0);
        $out = '';

        foreach ($body->childNodes as $node) {
            $out .= $document->saveHTML($node);
        }

        return $out;
    }

    private static function isPlainText(string $value): bool
    {
        // Any tag-looking markup means it came from the editor.
        return ! preg_match('/<\s*\/?\s*[a-z][a-z0-9]*(\s[^>]*)?\/?>/i', $value);
    }
}
