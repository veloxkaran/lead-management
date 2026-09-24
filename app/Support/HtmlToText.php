<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Flattens rich-text-editor HTML into readable plain text for places that
 * can't render HTML (client emails, Slack, activity feed, report exports).
 *
 * Unlike strip_tags() it keeps the structure — paragraphs become blank
 * lines, <br> a newline, list items "• " / "1. ", table cells " | ",
 * links "text (url)" — and decodes entities (&nbsp;, &amp;, …), so the
 * reader never sees raw tags or run-together sentences.
 */
class HtmlToText
{
    /** Indentation placeholder — survives tidy()'s whitespace collapsing, swapped for spaces at the end. */
    private const INDENT = "\x01";

    private const BLOCKS = [
        'p', 'div', 'section', 'article', 'header', 'footer', 'blockquote', 'pre',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'table', 'ul', 'ol', 'hr',
    ];

    public static function convert(?string $html): string
    {
        $html = (string) $html;

        if (trim($html) === '') {
            return '';
        }

        // Already plain text — nothing to parse, just normalise whitespace.
        if ($html === strip_tags($html)) {
            return self::tidy(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        // The XML prolog forces UTF-8 (DOMDocument otherwise assumes Latin-1).
        $document->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $document->getElementsByTagName('body')->item(0);

        return str_replace(self::INDENT, ' ', self::tidy($body ? self::walk($body) : strip_tags($html)));
    }

    private static function walk(DOMNode $node, int $depth = 0): string
    {
        $text = '';

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                // Source newlines/indentation are just HTML formatting.
                $text .= preg_replace('/\s+/u', ' ', $child->textContent);

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            $text .= match (true) {
                in_array($tag, ['script', 'style', 'head', 'title'], true) => '',
                $tag === 'br' => "\n",
                $tag === 'hr' => "\n\n",
                $tag === 'ul', $tag === 'ol' => "\n".self::listItems($child, $depth)."\n",
                $tag === 'tr' => "\n".self::tableRow($child, $depth),
                $tag === 'a' => self::link($child, $depth),
                $tag === 'img' => ($alt = trim($child->getAttribute('alt'))) !== '' ? "[{$alt}]" : '',
                in_array($tag, self::BLOCKS, true) => "\n\n".self::walk($child, $depth)."\n\n",
                default => self::walk($child, $depth),
            };
        }

        return $text;
    }

    private static function listItems(DOMElement $list, int $depth): string
    {
        $ordered = strtolower($list->tagName) === 'ol';
        $number = max(1, (int) ($list->getAttribute('start') ?: 1));
        $lines = [];

        foreach ($list->childNodes as $item) {
            if (! $item instanceof DOMElement || strtolower($item->tagName) !== 'li') {
                continue;
            }

            $marker = $ordered ? ($number++).'.' : '•';
            $content = trim(self::tidy(self::walk($item, $depth + 1)));

            // Continuation lines (nested lists, <br>) line up under the text;
            // each nesting level adds its own indent this way.
            $lines[] = $marker.' '.str_replace("\n", "\n".str_repeat(self::INDENT, 3), $content);
        }

        return implode("\n", $lines);
    }

    private static function tableRow(DOMElement $row, int $depth): string
    {
        $cells = [];

        foreach ($row->childNodes as $cell) {
            if ($cell instanceof DOMElement && in_array(strtolower($cell->tagName), ['td', 'th'], true)) {
                $cells[] = trim(preg_replace('/\s+/u', ' ', self::walk($cell, $depth)));
            }
        }

        return implode(' | ', $cells);
    }

    private static function link(DOMElement $link, int $depth): string
    {
        $label = trim(self::walk($link, $depth));
        $href = trim($link->getAttribute('href'));

        if ($href === '' || str_starts_with($href, '#') || str_starts_with(strtolower($href), 'javascript:')) {
            return $label;
        }

        $url = preg_replace('/^mailto:/i', '', $href);

        return ($label === '' || $label === $url) ? $url : "{$label} ({$url})";
    }

    private static function tidy(string $text): string
    {
        $text = str_replace(["\u{00A0}", "\r\n", "\r"], [' ', "\n", "\n"], $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text);
        $text = preg_replace('/ *\n */u', "\n", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }
}
