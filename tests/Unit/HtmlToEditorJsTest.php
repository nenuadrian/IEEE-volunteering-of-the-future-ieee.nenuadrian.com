<?php

namespace Tests\Unit;

use App\Support\EditorJsRenderer;
use App\Support\HtmlToEditorJs;
use PHPUnit\Framework\TestCase;

class HtmlToEditorJsTest extends TestCase
{
    public function test_empty_input_yields_no_blocks(): void
    {
        $this->assertSame([], HtmlToEditorJs::convert('')['blocks']);
        $this->assertSame([], HtmlToEditorJs::convert(null)['blocks']);
        $this->assertSame([], HtmlToEditorJs::convert('   ')['blocks']);
    }

    public function test_paragraphs_become_paragraph_blocks_keeping_inline_html(): void
    {
        $blocks = HtmlToEditorJs::convert('<p>Hello <strong>world</strong></p><p>Second</p>')['blocks'];

        $this->assertCount(2, $blocks);
        $this->assertSame('paragraph', $blocks[0]['type']);
        $this->assertSame('Hello <strong>world</strong>', $blocks[0]['data']['text']);
        $this->assertSame('Second', $blocks[1]['data']['text']);
    }

    public function test_headings_are_clamped_to_h2_through_h4(): void
    {
        $blocks = HtmlToEditorJs::convert('<h1>A</h1><h3>B</h3><h6>C</h6>')['blocks'];

        $this->assertSame([2, 3, 4], array_column(array_column($blocks, 'data'), 'level'));
    }

    public function test_loose_inline_text_is_wrapped_in_a_paragraph(): void
    {
        $blocks = HtmlToEditorJs::convert('Plain text with <a href="/x">a link</a>.')['blocks'];

        $this->assertCount(1, $blocks);
        $this->assertSame('paragraph', $blocks[0]['type']);
        $this->assertSame('Plain text with <a href="/x">a link</a>.', $blocks[0]['data']['text']);
    }

    public function test_nested_lists_are_preserved(): void
    {
        $blocks = HtmlToEditorJs::convert('<ul><li>One</li><li>Two<ul><li>Nested</li></ul></li></ul>')['blocks'];

        $this->assertSame('list', $blocks[0]['type']);
        $this->assertSame('unordered', $blocks[0]['data']['style']);
        $this->assertSame('Two', $blocks[0]['data']['items'][1]['content']);
        $this->assertSame('Nested', $blocks[0]['data']['items'][1]['items'][0]['content']);
    }

    public function test_table_with_headings_is_detected(): void
    {
        $html = '<table><thead><tr><th>A</th><th>B</th></tr></thead><tbody><tr><td>1</td><td>2</td></tr></tbody></table>';
        $data = HtmlToEditorJs::convert($html)['blocks'][0]['data'];

        $this->assertTrue($data['withHeadings']);
        $this->assertSame([['A', 'B'], ['1', '2']], $data['content']);
    }

    public function test_unknown_elements_are_preserved_as_raw_blocks(): void
    {
        $blocks = HtmlToEditorJs::convert('<div class="custom">keep me</div>')['blocks'];

        $this->assertSame('raw', $blocks[0]['type']);
        $this->assertStringContainsString('keep me', $blocks[0]['data']['html']);
    }

    public function test_round_trips_through_the_renderer(): void
    {
        $html = '<h2>Title</h2><p>Intro with <em>emphasis</em>.</p><ul><li>One</li><li>Two</li></ul>';

        // Mirror the real flow: the seed is JSON-encoded, edited in EditorJS, then
        // decoded back into associative arrays before the renderer ever sees it.
        $seed = json_encode(HtmlToEditorJs::convert($html));
        $reRendered = EditorJsRenderer::toHtml(json_decode($seed, true));

        $this->assertSame($html, $reRendered);
    }

    public function test_utf8_is_preserved(): void
    {
        $blocks = HtmlToEditorJs::convert('<p>café — résumé ✓</p>')['blocks'];

        $this->assertSame('café — résumé ✓', $blocks[0]['data']['text']);
    }
}
