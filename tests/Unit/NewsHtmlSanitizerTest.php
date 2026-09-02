<?php

namespace Tests\Unit;

use App\Services\NewsHtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NewsHtmlSanitizerTest extends TestCase
{
    public static function dangerousHtml(): iterable
    {
        yield 'script' => ['<p>Aman</p><script>alert(1)</script>', ['script', 'alert(1)']];
        yield 'event handler' => ['<img src="https://example.test/a.jpg" onerror="alert(1)">', ['onerror', 'alert(1)']];
        yield 'javascript link' => ['<a href="java&#x73;cript:alert(1)">tautan</a>', ['javascript:', 'href=']];
        yield 'svg' => ['<svg onload="alert(1)"><circle /></svg><p>Aman</p>', ['svg', 'onload', 'alert(1)']];
        yield 'iframe' => ['<iframe src="https://example.test"></iframe><p>Aman</p>', ['iframe']];
        yield 'style and event' => ['<p style="background:url(javascript:alert(1))" onclick="alert(2)">Aman</p>', ['style=', 'onclick', 'javascript:']];
    }

    #[DataProvider('dangerousHtml')]
    public function test_dangerous_html_is_neutralized(string $input, array $forbidden): void
    {
        $output = (new NewsHtmlSanitizer)->sanitize($input);

        foreach ($forbidden as $fragment) {
            $this->assertStringNotContainsStringIgnoringCase($fragment, $output);
        }
    }

    public function test_editorial_formatting_and_safe_links_remain(): void
    {
        $input = '<h2>Judul</h2><p class="lead">Teks <strong>tebal</strong> <em>miring</em>.</p>'
            .'<ul><li>Satu</li></ul><a href="https://example.test" target="_blank">Sumber</a>'
            .'<table><tbody><tr><td colspan="2">Data</td></tr></tbody></table>';

        $output = (new NewsHtmlSanitizer)->sanitize($input);

        $this->assertStringContainsString('<h2>Judul</h2>', $output);
        $this->assertStringContainsString('<strong>tebal</strong>', $output);
        $this->assertStringContainsString('href="https://example.test"', $output);
        $this->assertStringContainsString('rel="noopener noreferrer"', $output);
        $this->assertStringContainsString('<table>', $output);
    }

    public function test_inline_images_require_http_or_https(): void
    {
        $output = (new NewsHtmlSanitizer)->sanitize(
            '<img src="data:text/html;base64,PHNjcmlwdD4="><img src="https://example.test/image.jpg" alt="Aman">',
        );

        $this->assertStringNotContainsString('data:', $output);
        $this->assertStringContainsString('src="https://example.test/image.jpg"', $output);
    }
}
