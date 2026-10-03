<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Converts an HTML body string into EditorJS block JSON.
 *
 * This is the inverse of {@see EditorJsRenderer} and exists so that legacy /
 * seeded posts — which only have a hand-written HTML `body` and no
 * `editor_data` — open in the block editor as proper, editable paragraph /
 * header / list / quote blocks instead of a single opaque "Raw HTML" block.
 *
 * Block shapes intentionally mirror what the configured EditorJS tools (and
 * therefore EditorJsRenderer) expect, so a legacy post round-trips cleanly:
 * convert → edit → save regenerates an equivalent body.
 *
 * Inline content (paragraph/header/quote text, list items, table cells) is kept
 * verbatim; EditorJS sanitises it on load against each tool's inline whitelist.
 * Anything we don't model (divs, iframes, unknown elements) is preserved as a
 * `raw` block so no content is ever silently dropped.
 */
class HtmlToEditorJs
{
    /** Inline-level tags that should be folded into the surrounding paragraph. */
    private const INLINE_TAGS = [
        'a', 'b', 'strong', 'i', 'em', 'u', 's', 'mark', 'code', 'span',
        'br', 'sub', 'sup', 'small', 'abbr', 'cite', 'q', 'time', 'kbd', 'samp',
    ];

    /** @return array{blocks: array<int, array<string, mixed>>} */
    public static function convert(?string $html): array
    {
        $html = trim((string) $html);
        if ($html === '') {
            return ['blocks' => []];
        }

        $body = self::parse($html);
        if (! $body) {
            return ['blocks' => [['type' => 'raw', 'data' => ['html' => $html]]]];
        }

        $blocks = [];
        $inline = '';

        $flush = function () use (&$inline, &$blocks): void {
            $text = trim($inline);
            $inline = '';
            if ($text !== '') {
                $blocks[] = ['type' => 'paragraph', 'data' => ['text' => $text]];
            }
        };

        foreach ($body->childNodes as $node) {
            if ($node->nodeType === XML_TEXT_NODE) {
                $inline .= self::nodeHtml($node);

                continue;
            }

            if (! $node instanceof DOMElement) {
                continue; // comments, processing instructions, etc.
            }

            $tag = strtolower($node->nodeName);

            if (in_array($tag, self::INLINE_TAGS, true)) {
                $inline .= self::nodeHtml($node);

                continue;
            }

            $flush();

            $block = self::blockFor($tag, $node);
            if ($block !== null) {
                $blocks[] = $block;
            }
        }

        $flush();

        if ($blocks === []) {
            return ['blocks' => [['type' => 'raw', 'data' => ['html' => $html]]]];
        }

        return ['blocks' => $blocks];
    }

    /** Map a block-level element to an EditorJS block, or null to skip it. */
    private static function blockFor(string $tag, DOMElement $node): ?array
    {
        return match (true) {
            $tag === 'p'        => self::paragraph($node),
            $tag === 'hr'       => ['type' => 'delimiter', 'data' => []],
            in_array($tag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true) => self::header($tag, $node),
            in_array($tag, ['ul', 'ol'], true) => self::list($node),
            $tag === 'blockquote' => self::quote(self::inner($node), ''),
            $tag === 'pre'      => ['type' => 'code', 'data' => ['code' => $node->textContent]],
            $tag === 'img'      => self::image($node),
            $tag === 'table'    => self::table($node),
            $tag === 'figure'   => self::figure($node),
            default             => ['type' => 'raw', 'data' => ['html' => self::nodeHtml($node)]],
        };
    }

    private static function paragraph(DOMElement $node): ?array
    {
        // A paragraph that is just a wrapper around a single image is really an image block.
        $img = self::onlyChildImage($node);
        if ($img) {
            return self::image($img);
        }

        $text = self::inner($node);

        return $text === '' ? null : ['type' => 'paragraph', 'data' => ['text' => $text]];
    }

    private static function header(string $tag, DOMElement $node): ?array
    {
        $text = self::inner($node);
        if ($text === '') {
            return null;
        }
        $level = max(2, min(4, (int) substr($tag, 1))); // h1 is reserved for the title; clamp to h2–h4

        return ['type' => 'header', 'data' => ['text' => $text, 'level' => $level]];
    }

    private static function list(DOMElement $node): array
    {
        return [
            'type' => 'list',
            'data' => [
                'style' => strtolower($node->nodeName) === 'ol' ? 'ordered' : 'unordered',
                'items' => self::listItems($node),
            ],
        ];
    }

    /** @return array<int, array{content: string, meta: array, items: array}> */
    private static function listItems(DOMElement $listEl): array
    {
        $items = [];
        foreach ($listEl->childNodes as $li) {
            if (! $li instanceof DOMElement || strtolower($li->nodeName) !== 'li') {
                continue;
            }

            $content = '';
            $children = [];
            foreach ($li->childNodes as $child) {
                if ($child instanceof DOMElement && in_array(strtolower($child->nodeName), ['ul', 'ol'], true)) {
                    $children = self::listItems($child);

                    continue;
                }
                $content .= self::nodeHtml($child);
            }

            $items[] = [
                'content' => trim($content),
                'meta' => new \stdClass, // EditorJS list items expect an object, not an array
                'items' => $children,
            ];
        }

        return $items;
    }

    private static function quote(string $text, string $caption): ?array
    {
        if (trim($text) === '') {
            return null;
        }

        return ['type' => 'quote', 'data' => ['text' => trim($text), 'caption' => trim($caption), 'alignment' => 'left']];
    }

    private static function image(DOMElement $img, string $caption = ''): array
    {
        $classes = explode(' ', (string) $img->getAttribute('class'));

        return [
            'type' => 'image',
            'data' => [
                'file' => ['url' => $img->getAttribute('src')],
                'caption' => trim($caption),
                'withBorder' => in_array('img-bordered', $classes, true),
                'stretched' => in_array('img-stretched', $classes, true),
                'withBackground' => in_array('img-bg', $classes, true),
            ],
        ];
    }

    private static function table(DOMElement $node): array
    {
        $rows = [];
        $withHeadings = false;

        foreach ($node->getElementsByTagName('tr') as $tr) {
            $cells = [];
            $isHead = false;
            foreach ($tr->childNodes as $cell) {
                if (! $cell instanceof DOMElement) {
                    continue;
                }
                $name = strtolower($cell->nodeName);
                if ($name !== 'td' && $name !== 'th') {
                    continue;
                }
                if ($name === 'th') {
                    $isHead = true;
                }
                $cells[] = self::inner($cell);
            }
            if ($cells === []) {
                continue;
            }
            if ($isHead && $rows === []) {
                $withHeadings = true;
            }
            $rows[] = $cells;
        }

        return ['type' => 'table', 'data' => ['withHeadings' => $withHeadings, 'content' => $rows]];
    }

    /** A <figure> can wrap a quote, an image, or something we don't model. */
    private static function figure(DOMElement $node): ?array
    {
        $blockquote = self::firstTag($node, 'blockquote');
        if ($blockquote) {
            $caption = self::firstTag($node, 'figcaption');

            return self::quote(self::inner($blockquote), $caption ? self::inner($caption) : '');
        }

        $img = self::firstTag($node, 'img');
        if ($img && ! self::firstTag($node, 'iframe')) {
            $caption = self::firstTag($node, 'figcaption');

            return self::image($img, $caption ? self::inner($caption) : '');
        }

        // Embeds (iframes) and anything else: preserve verbatim.
        return ['type' => 'raw', 'data' => ['html' => self::nodeHtml($node)]];
    }

    // --- DOM helpers -------------------------------------------------------

    private static function parse(string $html): ?DOMNode
    {
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        // The XML pragma forces UTF-8 decoding; without it DOMDocument assumes Latin-1.
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $doc->getElementsByTagName('body')->item(0);
    }

    /** Serialise a node (and descendants) back to its HTML string. */
    private static function nodeHtml(DOMNode $node): string
    {
        return $node->ownerDocument?->saveHTML($node) ?? '';
    }

    /** Inner HTML of an element, with surrounding whitespace trimmed. */
    private static function inner(DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= self::nodeHtml($child);
        }

        return trim($html);
    }

    private static function firstTag(DOMElement $node, string $tag): ?DOMElement
    {
        return $node->getElementsByTagName($tag)->item(0);
    }

    /** If a node's only meaningful child is an <img>, return it. */
    private static function onlyChildImage(DOMElement $node): ?DOMElement
    {
        $img = null;
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE && trim($child->textContent) === '') {
                continue;
            }
            if ($child instanceof DOMElement && strtolower($child->nodeName) === 'img') {
                if ($img) {
                    return null; // more than one image
                }
                $img = $child;

                continue;
            }

            return null; // some other content alongside the image
        }

        return $img;
    }
}
