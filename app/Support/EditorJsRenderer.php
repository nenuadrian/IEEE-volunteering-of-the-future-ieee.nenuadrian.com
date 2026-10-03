<?php

namespace App\Support;

/**
 * Renders EditorJS block JSON to an HTML string.
 *
 * The output is ALWAYS passed through the purifier 'content' profile by the
 * caller, which is the real XSS boundary. Therefore:
 *   - Inline-formatted text fields (paragraph/header/quote text, list item
 *     content, table cells, captions) already contain inline HTML emitted by
 *     EditorJS (<b>, <i>, <a>, <mark>, <code>, <br>) and are written VERBATIM.
 *     Escaping them would render literal "&lt;b&gt;".
 *   - Plain-text fields (code block contents) are htmlspecialchars-escaped.
 *
 * @see \App\Http\Controllers\Admin\PostController
 * @see config/purifier.php  (the 'content' profile)
 */
class EditorJsRenderer
{
    /** Embed URLs we are willing to turn into an <iframe> (matches purifier SafeIframe). */
    private const EMBED_PATTERN = '%^(https?:)?//(www\.youtube(-nocookie)?\.com/embed/|player\.vimeo\.com/video/)%';

    public static function toHtml(?array $data): string
    {
        if (! is_array($data) || empty($data['blocks']) || ! is_array($data['blocks'])) {
            return '';
        }

        $html = '';

        foreach ($data['blocks'] as $block) {
            if (! is_array($block) || ! isset($block['type'])) {
                continue;
            }

            $d = $block['data'] ?? [];
            $html .= match ($block['type']) {
                'paragraph' => self::paragraph($d),
                'header'    => self::header($d),
                'list'      => self::list($d),
                'quote'     => self::quote($d),
                'table'     => self::table($d),
                'image'     => self::image($d),
                'embed'     => self::embed($d),
                'code'      => self::code($d),
                'delimiter' => '<hr>',
                'raw'       => (string) ($d['html'] ?? ''),
                default     => '',
            };
        }

        return $html;
    }

    private static function paragraph(array $d): string
    {
        $text = (string) ($d['text'] ?? '');

        return $text === '' ? '' : '<p>'.$text.'</p>';
    }

    private static function header(array $d): string
    {
        $level = (int) ($d['level'] ?? 2);
        $level = max(2, min(4, $level)); // h1 is reserved for the page title; clamp to h2–h4
        $text = (string) ($d['text'] ?? '');

        return $text === '' ? '' : "<h{$level}>{$text}</h{$level}>";
    }

    private static function list(array $d): string
    {
        $style = $d['style'] ?? 'unordered';
        $items = is_array($d['items'] ?? null) ? $d['items'] : [];

        return self::listItems($items, $style);
    }

    /** Recursively render EditorJS v2 list items ({content, meta, items}); tolerates v1 string items. */
    private static function listItems(array $items, string $style): string
    {
        if (empty($items)) {
            return '';
        }

        $checklist = $style === 'checklist';
        $tag = $style === 'ordered' ? 'ol' : 'ul';
        $open = $checklist ? '<ul class="checklist">' : "<{$tag}>";
        $close = $checklist ? '</ul>' : "</{$tag}>";

        $html = $open;
        foreach ($items as $item) {
            if (is_string($item)) {            // v1 fallback
                $content = $item;
                $children = [];
                $checked = false;
            } else {
                $content = (string) ($item['content'] ?? '');
                $children = is_array($item['items'] ?? null) ? $item['items'] : [];
                $checked = (bool) ($item['meta']['checked'] ?? false);
            }

            $liClass = $checklist ? ' class="task'.($checked ? ' done' : '').'"' : '';
            $html .= "<li{$liClass}>".$content;
            $html .= self::listItems($children, $style); // nested
            $html .= '</li>';
        }

        return $html.$close;
    }

    private static function quote(array $d): string
    {
        $text = (string) ($d['text'] ?? '');
        if ($text === '') {
            return '';
        }
        $caption = (string) ($d['caption'] ?? '');
        $cap = $caption !== '' ? '<figcaption>'.$caption.'</figcaption>' : '';

        return '<figure class="quote"><blockquote>'.$text.'</blockquote>'.$cap.'</figure>';
    }

    private static function table(array $d): string
    {
        $rows = is_array($d['content'] ?? null) ? $d['content'] : [];
        if (empty($rows)) {
            return '';
        }
        $withHeadings = (bool) ($d['withHeadings'] ?? false);

        $html = '<table>';
        $body = '';
        foreach (array_values($rows) as $i => $row) {
            $row = is_array($row) ? $row : [];
            if ($withHeadings && $i === 0) {
                $cells = '';
                foreach ($row as $cell) {
                    $cells .= '<th>'.(string) $cell.'</th>';
                }
                $html .= '<thead><tr>'.$cells.'</tr></thead>';

                continue;
            }
            $cells = '';
            foreach ($row as $cell) {
                $cells .= '<td>'.(string) $cell.'</td>';
            }
            $body .= '<tr>'.$cells.'</tr>';
        }

        return $html.'<tbody>'.$body.'</tbody></table>';
    }

    private static function image(array $d): string
    {
        $url = (string) ($d['file']['url'] ?? $d['url'] ?? '');
        if ($url === '') {
            return '';
        }
        $caption = (string) ($d['caption'] ?? '');

        $classes = [];
        if (! empty($d['stretched']))      { $classes[] = 'img-stretched'; }
        if (! empty($d['withBorder']))     { $classes[] = 'img-bordered'; }
        if (! empty($d['withBackground'])) { $classes[] = 'img-bg'; }
        $class = $classes ? ' class="'.implode(' ', $classes).'"' : '';

        // alt is a plain-text field -> escape; src is a relative /storage URL.
        $alt = e(strip_tags($caption));
        $img = '<img src="'.e($url).'" alt="'.$alt.'">';
        $cap = $caption !== '' ? '<figcaption>'.$caption.'</figcaption>' : '';

        return '<figure'.$class.'>'.$img.$cap.'</figure>';
    }

    private static function embed(array $d): string
    {
        $src = (string) ($d['embed'] ?? '');
        if ($src === '' || ! preg_match(self::EMBED_PATTERN, $src)) {
            return ''; // only youtube/vimeo embeds; anything else is dropped (purifier would strip it too)
        }
        $caption = (string) ($d['caption'] ?? '');
        $cap = $caption !== '' ? '<figcaption>'.$caption.'</figcaption>' : '';

        $iframe = '<iframe src="'.e($src).'" frameborder="0" allowfullscreen></iframe>';

        return '<figure class="embed">'.$iframe.$cap.'</figure>';
    }

    private static function code(array $d): string
    {
        $code = (string) ($d['code'] ?? '');

        // Plain text -> must be escaped before wrapping.
        return '<pre><code>'.e($code).'</code></pre>';
    }
}
