<?php

declare(strict_types=1);

namespace OCA\TalkContentBrowser\Tests\Controller;

use PHPUnit\Framework\TestCase;

/**
 * Tests for OgMetaController private methods:
 * - extractOgField (OG/twitter meta regex extraction)
 * - extractHtmlTitle (HTML <title> fallback)
 * - readCache (TTL logic)
 *
 * All methods are accessed via ReflectionMethod since they are private.
 */
class OgMetaControllerTest extends TestCase
{
    private \ReflectionClass $ref;
    private object $controller;

    protected function setUp(): void
    {
        // The bootstrap (loaded by phpunit.xml) has already defined OCP stubs.
        // Load the controller file; the guard at the top prevents double-loading.
        require_once __DIR__ . '/../../../lib/Controller/OgMetaController.php';

        // Instantiate via reflection — no constructor (we won't call it)
        $this->ref = new \ReflectionClass(\OCA\TalkContentBrowser\Controller\OgMetaController::class);
        $this->controller = $this->ref->newInstanceWithoutConstructor();
    }

    /** Call a private method by name, forwarding arguments. */
    private function call(string $method, mixed ...$args): mixed
    {
        $m = $this->ref->getMethod($method);
        $m->setAccessible(true);
        return $m->invoke($this->controller, ...$args);
    }

    // ─── extractOgField ───────────────────────────────────────────────────────

    public function testExtractOgFieldPropertyBeforeContent(): void
    {
        $html = '<meta property="og:title" content="Hello World">';
        $result = $this->call('extractOgField', $html, ['og:title', 'twitter:title']);
        $this->assertSame('Hello World', $result);
    }

    public function testExtractOgFieldContentBeforeProperty(): void
    {
        $html = '<meta content="Hello World" property="og:title">';
        $result = $this->call('extractOgField', $html, ['og:title', 'twitter:title']);
        $this->assertSame('Hello World', $result);
    }

    public function testExtractOgFieldFallsBackToTwitterTitle(): void
    {
        $html = '<meta name="twitter:title" content="Twitter Title">';
        $result = $this->call('extractOgField', $html, ['og:title', 'twitter:title']);
        $this->assertSame('Twitter Title', $result);
    }

    public function testExtractOgFieldReturnsNullWhenNoMatchingTags(): void
    {
        $html = '<html><head><title>Page</title></head></html>';
        $result = $this->call('extractOgField', $html, ['og:title', 'twitter:title']);
        $this->assertNull($result);
    }

    public function testExtractOgFieldDecodesHtmlEntities(): void
    {
        $html = '<meta property="og:title" content="Hello &amp; World &lt;3&gt;">';
        $result = $this->call('extractOgField', $html, ['og:title']);
        $this->assertSame('Hello & World <3>', $result);
    }

    // ─── extractHtmlTitle ─────────────────────────────────────────────────────

    public function testExtractHtmlTitleReturnsTitle(): void
    {
        $html = '<html><head><title>My Page Title</title></head><body></body></html>';
        $result = $this->call('extractHtmlTitle', $html);
        $this->assertSame('My Page Title', $result);
    }

    public function testExtractHtmlTitleReturnsNullWhenNoTitle(): void
    {
        $html = '<html><head></head><body><p>No title here</p></body></html>';
        $result = $this->call('extractHtmlTitle', $html);
        $this->assertNull($result);
    }

    // ─── readCache TTL logic ──────────────────────────────────────────────────

    /**
     * readCache calls $this->getCacheFolder() which is private.
     * We inject a fake $appData via reflection that returns a fake folder
     * whose getFolder() returns a fake ISimpleFolder with pre-seeded data.
     */
    private function makeControllerWithCacheEntry(array $data): object
    {
        $json = json_encode($data);

        $fakeFile = new class($json) implements \OCP\Files\SimpleFS\ISimpleFile {
            public function __construct(private string $content) {}
            public function getContent(): string { return $this->content; }
            public function putContent(string $data): void {}
        };

        $fakeFolder = new class($fakeFile) implements \OCP\Files\SimpleFS\ISimpleFolder {
            public function __construct(private \OCP\Files\SimpleFS\ISimpleFile $file) {}
            public function getFile(string $name): \OCP\Files\SimpleFS\ISimpleFile { return $this->file; }
            public function newFile(string $name, string $content = ''): \OCP\Files\SimpleFS\ISimpleFile { return $this->file; }
        };

        $fakeAppData = new class($fakeFolder) implements \OCP\Files\IAppData {
            public function __construct(private \OCP\Files\SimpleFS\ISimpleFolder $folder) {}
            public function getFolder(string $name): \OCP\Files\SimpleFS\ISimpleFolder { return $this->folder; }
            public function newFolder(string $name): \OCP\Files\SimpleFS\ISimpleFolder { return $this->folder; }
        };

        $ctrl = $this->ref->newInstanceWithoutConstructor();

        // Inject the fake appData into the private $appData property
        $prop = $this->ref->getProperty('appData');
        $prop->setAccessible(true);
        $prop->setValue($ctrl, $fakeAppData);

        return $ctrl;
    }

    private function invokeReadCache(object $ctrl, string $key): mixed
    {
        $m = (new \ReflectionClass($ctrl))->getMethod('readCache');
        $m->setAccessible(true);
        return $m->invoke($ctrl, $key);
    }

    public function testReadCacheFreshSuccessfulEntry(): void
    {
        $ctrl = $this->makeControllerWithCacheEntry([
            'ts'          => time() - 100, // 100 seconds ago — well within 7-day TTL
            'title'       => 'Cached Title',
            'description' => 'Cached Desc',
        ]);
        $result = $this->invokeReadCache($ctrl, 'anykey');
        $this->assertSame(['title' => 'Cached Title', 'description' => 'Cached Desc'], $result);
    }

    public function testReadCacheStaleSuccessfulEntry(): void
    {
        $ctrl = $this->makeControllerWithCacheEntry([
            'ts'          => time() - (7 * 86400 + 1), // just over 7 days
            'title'       => 'Old Title',
            'description' => null,
        ]);
        $result = $this->invokeReadCache($ctrl, 'anykey');
        $this->assertNull($result);
    }

    public function testReadCacheFreshEmptySentinel(): void
    {
        $ctrl = $this->makeControllerWithCacheEntry([
            'ts'          => time() - 100, // 100 seconds ago — within 1-hour TTL
            'title'       => null,
            'description' => null,
        ]);
        $result = $this->invokeReadCache($ctrl, 'anykey');
        $this->assertSame(['title' => null, 'description' => null], $result);
    }

    public function testReadCacheStaleEmptySentinel(): void
    {
        $ctrl = $this->makeControllerWithCacheEntry([
            'ts'          => time() - (3600 + 1), // just over 1 hour
            'title'       => null,
            'description' => null,
        ]);
        $result = $this->invokeReadCache($ctrl, 'anykey');
        $this->assertNull($result);
    }
}
