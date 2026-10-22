<?php

namespace Tests\Unit;

use App\Services\ArticleHtmlSanitizer;
use PHPUnit\Framework\TestCase;

class ArticleHtmlSanitizerTest extends TestCase
{
    private ArticleHtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sanitizer = new ArticleHtmlSanitizer();
    }

    public function test_preserves_allowed_html(): void
    {
        $html = '<p>Hello <strong>world</strong> and <em>Laravel</em></p>';

        $this->assertSame(
            $html,
            $this->sanitizer->sanitize($html)
        );
    }

    public function test_removes_script_elements(): void
    {
        $html = '<p>Safe</p><script>alert(1)</script>';

        $result = $this->sanitizer->sanitize($html);

        $this->assertStringContainsString('<p>Safe</p>', $result);
        $this->assertStringNotContainsString('<script', $result);
        $this->assertStringNotContainsString('alert(1)', $result);
    }

    public function test_removes_event_handlers(): void
    {
        $html = '<img src="https://example.com/a.jpg" onerror="alert(1)" alt="test">';

        $result = $this->sanitizer->sanitize($html);

        $this->assertStringNotContainsString('onerror', $result);
        $this->assertStringNotContainsString('alert(1)', $result);
        $this->assertStringContainsString('alt="test"', $result);
    }

    public function test_blocks_javascript_urls(): void
    {
        $html = '<a href="javascript:alert(1)">Click</a>';

        $result = $this->sanitizer->sanitize($html);

        $this->assertStringNotContainsString('javascript:', $result);
        $this->assertStringContainsString('Click', $result);
    }

    public function test_preserves_safe_links(): void
    {
        $html = '<a href="https://example.com">Website</a>';

        $result = $this->sanitizer->sanitize($html);

        $this->assertStringContainsString(
            'href="https://example.com"',
            $result
        );
    }

    public function test_removes_disallowed_iframe(): void
    {
        $html = '<p>Content</p><iframe src="https://example.com"></iframe>';

        $result = $this->sanitizer->sanitize($html);

        $this->assertStringContainsString('Content', $result);
        $this->assertStringNotContainsString('<iframe', $result);
    }

    public function test_preserves_allowed_lists_and_tables(): void
    {
        $html = '<ul><li>Item</li></ul>'
            . '<table><tbody><tr><td>Cell</td></tr></tbody></table>';

        $result = $this->sanitizer->sanitize($html);

        $this->assertStringContainsString('<li>Item</li>', $result);
        $this->assertStringContainsString('<td>Cell</td>', $result);
    }

    public function test_handles_null_and_empty_content(): void
    {
        $this->assertNull($this->sanitizer->sanitize(null));
        $this->assertSame('', $this->sanitizer->sanitize(''));
    }
}
