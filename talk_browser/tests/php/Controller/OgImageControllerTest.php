<?php

declare(strict_types=1);

namespace OCA\TalkContentBrowser\Tests\Controller;

use PHPUnit\Framework\TestCase;

/**
 * Tests for OgImageController private methods:
 * - resolveUrl (URL resolution for OG image src)
 * - extractOgImage (OG/twitter image regex extraction)
 */
class OgImageControllerTest extends TestCase
{
    private \ReflectionClass $ref;
    private object $controller;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../lib/Controller/OgImageController.php';

        $this->ref = new \ReflectionClass(\OCA\TalkContentBrowser\Controller\OgImageController::class);
        $this->controller = $this->ref->newInstanceWithoutConstructor();
    }

    /** Call a private method by name, forwarding arguments. */
    private function call(string $method, mixed ...$args): mixed
    {
        $m = $this->ref->getMethod($method);
        $m->setAccessible(true);
        return $m->invoke($this->controller, ...$args);
    }

    // ─── resolveUrl ───────────────────────────────────────────────────────────

    public function testResolveUrlKeepsAbsoluteHttps(): void
    {
        $result = $this->call('resolveUrl', 'https://cdn.example.com/img.png', 'https://example.com/page');
        $this->assertSame('https://cdn.example.com/img.png', $result);
    }

    public function testResolveUrlKeepsAbsoluteHttp(): void
    {
        $result = $this->call('resolveUrl', 'http://cdn.example.com/img.png', 'http://example.com/page');
        $this->assertSame('http://cdn.example.com/img.png', $result);
    }

    public function testResolveUrlProtocolRelative(): void
    {
        $result = $this->call('resolveUrl', '//cdn.example.com/img.png', 'https://example.com/page');
        $this->assertSame('https://cdn.example.com/img.png', $result);
    }

    public function testResolveUrlAbsolutePath(): void
    {
        $result = $this->call('resolveUrl', '/images/og.png', 'https://example.com/page/about');
        $this->assertSame('https://example.com/images/og.png', $result);
    }

    public function testResolveUrlRelativePath(): void
    {
        $result = $this->call('resolveUrl', 'og.png', 'https://example.com/page/about');
        $this->assertSame('https://example.com/page/og.png', $result);
    }

    public function testResolveUrlRejectsJavascriptScheme(): void
    {
        $result = $this->call('resolveUrl', 'javascript:alert(1)', 'https://example.com/page');
        $this->assertNull($result);
    }

    public function testResolveUrlRejectsDataScheme(): void
    {
        $result = $this->call('resolveUrl', 'data:image/png;base64,abc', 'https://example.com/page');
        $this->assertNull($result);
    }

    public function testResolveUrlReturnsNullForMissingBaseScheme(): void
    {
        // Base URL without a scheme — can't resolve relative paths
        $result = $this->call('resolveUrl', '/img.png', 'not-a-url');
        $this->assertNull($result);
    }

    // ─── extractOgImage ───────────────────────────────────────────────────────

    public function testExtractOgImageFindsOgImage(): void
    {
        $html = '<meta property="og:image" content="https://example.com/img.png">';
        $result = $this->call('extractOgImage', $html, 'https://example.com/page');
        $this->assertSame('https://example.com/img.png', $result);
    }

    public function testExtractOgImageFindsOgImageContentFirst(): void
    {
        $html = '<meta content="https://example.com/img.png" property="og:image">';
        $result = $this->call('extractOgImage', $html, 'https://example.com/page');
        $this->assertSame('https://example.com/img.png', $result);
    }

    public function testExtractOgImageFallsBackToTwitterImage(): void
    {
        $html = '<meta name="twitter:image" content="https://example.com/twitter.png">';
        $result = $this->call('extractOgImage', $html, 'https://example.com/page');
        $this->assertSame('https://example.com/twitter.png', $result);
    }

    public function testExtractOgImageReturnsNullWhenNoImage(): void
    {
        $html = '<html><head><title>No image</title></head></html>';
        $result = $this->call('extractOgImage', $html, 'https://example.com/page');
        $this->assertNull($result);
    }
}
