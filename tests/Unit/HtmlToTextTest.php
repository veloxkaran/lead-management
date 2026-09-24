<?php

namespace Tests\Unit;

use App\Support\HtmlToText;
use PHPUnit\Framework\TestCase;

class HtmlToTextTest extends TestCase
{
    public function test_plain_text_passes_through_trimmed(): void
    {
        $this->assertSame('Just text', HtmlToText::convert('  Just text '));
        $this->assertSame('', HtmlToText::convert(null));
        $this->assertSame('', HtmlToText::convert('<p> </p>'));
    }

    public function test_paragraphs_and_line_breaks_are_kept(): void
    {
        $this->assertSame(
            "First paragraph\n\nSecond line one\nSecond line two",
            HtmlToText::convert('<p>First paragraph</p><p>Second line one<br>Second line two</p>'),
        );
    }

    public function test_list_items_are_not_run_together(): void
    {
        // The real-world case: strip_tags() produced "20,000 charactersEmail:Maximum…".
        $this->assertSame(
            "• Editor: 20,000 characters\n• Email: 500 KB",
            HtmlToText::convert('<ul><li>Editor: 20,000 characters</li><li>Email: 500 KB</li></ul>'),
        );

        $this->assertSame(
            "1. One\n2. Two\n   • nested",
            HtmlToText::convert('<ol><li>One</li><li>Two<ul><li>nested</li></ul></li></ol>'),
        );
    }

    public function test_inline_styling_tags_and_entities_are_removed(): void
    {
        $html = '<p><span style="background-color:transparent;color:rgb(0,0,0);">System does </span>'
            .'<strong>not</strong>&nbsp;support &amp; encash — <span>❌ Not supported</span></p>';

        $this->assertSame('System does not support & encash — ❌ Not supported', HtmlToText::convert($html));
    }

    public function test_links_keep_their_url_and_tables_their_cells(): void
    {
        $this->assertSame('Read the docs (https://example.test/docs)', HtmlToText::convert('<p>Read <a href="https://example.test/docs">the docs</a></p>'));
        $this->assertSame('https://example.test', HtmlToText::convert('<a href="https://example.test">https://example.test</a>'));
        $this->assertSame("Leave | Status\nSubstitute | No", HtmlToText::convert('<table><tr><th>Leave</th><th>Status</th></tr><tr><td>Substitute</td><td>No</td></tr></table>'));
    }

    public function test_script_and_style_content_is_dropped(): void
    {
        $this->assertSame('Visible', HtmlToText::convert('<style>p{color:red}</style><p>Visible</p><script>alert(1)</script>'));
    }
}
