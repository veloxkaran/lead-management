<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Mews\Purifier\Facades\Purifier;

/**
 * An email campaign's body, written in the rich-text editor (stored as
 * sanitized HTML, message_format "html") or — for SMS and older email
 * campaigns — plain text ("text").
 */
class CampaignBody
{
    /**
     * Editor HTML → what's saved: Quill's list markup fixed up for email,
     * then sanitized. Returns '' when there's no actual text, so the
     * "message is required" rule catches an empty editor.
     */
    public static function fromEditor(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '' || trim(HtmlToText::convert($html)) === '') {
            return '';
        }

        return trim(Purifier::clean(self::fixQuillLists($html)));
    }

    /**
     * The body for one recipient: HTML for the email (merge values
     * escaped, so a name can't inject markup) and plain text for the
     * text part.
     *
     * @return array{html: string, text: string}
     */
    public static function render(string $message, string $format, array $variables): array
    {
        if ($format === 'html') {
            $html = self::merge($message, array_map(fn ($v) => e((string) ($v ?? '')), $variables));

            return ['html' => $html, 'text' => HtmlToText::convert($html)];
        }

        $text = self::merge($message, $variables);

        return ['html' => nl2br(e($text)), 'text' => $text];
    }

    private static function merge(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace('{{'.$key.'}}', (string) ($value ?? ''), $text);
        }

        return preg_replace('/\{\{\s*[a-z0-9_]+\s*\}\}/i', '', $text);
    }

    /**
     * Quill 2 writes every list as <ol>, marking bullet items with
     * data-list="bullet" — an attribute the sanitizer (and mail apps)
     * drop, which would turn bullets into numbers. Bullet runs become <ul>.
     */
    private static function fixQuillLists(string $html): string
    {
        if (! str_contains($html, 'data-list=')) {
            return $html;
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        foreach (iterator_to_array((new DOMXPath($document))->query('//ol')) as $ol) {
            /** @var DOMElement $ol */
            $items = iterator_to_array($ol->getElementsByTagName('li'));

            if ($items && collect($items)->every(fn (DOMElement $li) => $li->getAttribute('data-list') === 'bullet')) {
                $ul = $document->createElement('ul');
                while ($ol->firstChild) {
                    $ul->appendChild($ol->firstChild);
                }
                $ol->parentNode->replaceChild($ul, $ol);
            }
        }

        $body = $document->getElementsByTagName('body')->item(0);
        $out = '';
        foreach ($body?->childNodes ?? [] as $child) {
            $out .= $document->saveHTML($child);
        }

        return $out;
    }
}
