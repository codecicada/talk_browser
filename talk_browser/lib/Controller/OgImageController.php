<?php

declare(strict_types=1);

namespace OCA\TalkContentBrowser\Controller;

use OCA\TalkContentBrowser\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\Response;
use OCP\Files\IAppData;
use OCP\Files\AppData\IAppDataFactory;
use OCP\IRequest;

if (class_exists(__NAMESPACE__ . '\\OgImageController', false)) {
    return;
}

/**
 * Server-side proxy that fetches and caches OpenGraph images for external URLs.
 *
 * Client-side img-src is restricted by Nextcloud's CSP to 'self', so external
 * favicon/OG images must be proxied through this endpoint.
 *
 * Flow:
 *   1. Receive ?url=<encoded-external-url>
 *   2. Check disk cache (APP_DATA/og-cache/<hash>.img + .meta); serve if fresh
 *   3. Fetch page HTML, extract <meta property="og:image"> (or twitter:image)
 *   4. Fetch the image bytes, validate content-type
 *   5. Store to cache with TTL = 7 days
 *   6. Return image bytes with appropriate Content-Type header
 */
class OgImageController extends Controller {
    private const CACHE_TTL       = 604800; // 7 days for successful images
    private const CACHE_TTL_EMPTY = 3600;   // 1 hour for "no OG image found" sentinels
    private const FETCH_TIMEOUT   = 10;     // seconds
    private const MAX_IMAGE_BYTES = 5 * 1024 * 1024; // 5 MiB

    private IAppData $appData;

    public function __construct(
        IRequest $request,
        IAppDataFactory $appDataFactory,
    ) {
        parent::__construct(Application::APP_ID, $request);
        // Get an app-scoped IAppData instance for talk_browser
        $this->appData = $appDataFactory->get(Application::APP_ID);
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function proxy(): Response {
        $rawUrl = $this->request->getParam('url', '');

        // --- Validate input URL ---
        if ($rawUrl === '') {
            return $this->emptyResponse();
        }

        $parsed = parse_url($rawUrl);
        if (
            !isset($parsed['scheme'], $parsed['host'])
            || !in_array(strtolower($parsed['scheme']), ['http', 'https'], true)
        ) {
            return $this->emptyResponse();
        }

        $cacheKey = hash('sha256', $rawUrl);

        // --- Try cache ---
        $cached = $this->readCache($cacheKey);
        if ($cached !== null) {
            return $this->imageResponse($cached['data'], $cached['mime']);
        }

        // --- Fetch remote page HTML ---
        $html = $this->fetchUrl($rawUrl, 'text/html,application/xhtml+xml');
        if ($html === null) {
            return $this->cacheAndReturn($cacheKey, '', '');
        }

        // --- Extract og:image / twitter:image ---
        $imageUrl = $this->extractOgImage($html, $rawUrl);
        if ($imageUrl === null) {
            return $this->cacheAndReturn($cacheKey, '', '');
        }

        // --- Fetch image bytes ---
        [$imageData, $mime] = $this->fetchImage($imageUrl);
        if ($imageData === null) {
            return $this->cacheAndReturn($cacheKey, '', '');
        }

        return $this->cacheAndReturn($cacheKey, $imageData, $mime);
    }

    // -------------------------------------------------------------------------
    // Cache helpers
    // -------------------------------------------------------------------------

    private function readCache(string $key): ?array {
        try {
            $folder = $this->getCacheFolder();
            $metaFile  = $folder->getFile($key . '.meta');
            $imgFile   = $folder->getFile($key . '.img');

            $meta = json_decode($metaFile->getContent(), true);
            if (!is_array($meta)) {
                return null;
            }

            // Expired? Use shorter TTL for empty sentinels so failed URLs get retried sooner
            $isEmptySentinel = ($meta['mime'] ?? '') === '';
            $ttl = $isEmptySentinel ? self::CACHE_TTL_EMPTY : self::CACHE_TTL;
            if (time() - ($meta['ts'] ?? 0) > $ttl) {
                return null;
            }

            // Empty sentinel (OG image not found for this URL)
            if ($isEmptySentinel) {
                return ['data' => '', 'mime' => ''];
            }

            return [
                'data' => $imgFile->getContent(),
                'mime' => $meta['mime'],
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function writeCache(string $key, string $data, string $mime): void {
        try {
            $folder = $this->getCacheFolder();
            $meta   = json_encode(['ts' => time(), 'mime' => $mime]);

            // Write / overwrite meta
            try {
                $folder->getFile($key . '.meta')->putContent($meta);
            } catch (\OCP\Files\NotFoundException) {
                $folder->newFile($key . '.meta', $meta);
            }

            // Write / overwrite image bytes (even if empty sentinel)
            try {
                $folder->getFile($key . '.img')->putContent($data);
            } catch (\OCP\Files\NotFoundException) {
                $folder->newFile($key . '.img', $data);
            }
        } catch (\Throwable) {
            // Cache write failure is non-fatal
        }
    }

    private function getCacheFolder(): \OCP\Files\SimpleFS\ISimpleFolder {
        try {
            return $this->appData->getFolder('og-cache');
        } catch (\OCP\Files\NotFoundException) {
            return $this->appData->newFolder('og-cache');
        }
    }

    // -------------------------------------------------------------------------
    // HTTP fetch helpers
    // -------------------------------------------------------------------------

    private const TWITTER_HOSTS = ['x.com', 'www.x.com', 'twitter.com', 'www.twitter.com'];

    /**
     * Fetch a URL and return its body, or null on failure.
     * Accepts an optional Accept header value.
     *
     * X.com/Twitter are JS SPAs with no server-rendered OG tags. We rewrite
     * those URLs to fxtwitter.com (which serves OG-enriched embeds) and use
     * a bot UA so fxtwitter doesn't redirect us back to the SPA.
     */
    private function fetchUrl(string $url, string $accept = '*/*'): ?string {
        $parsed    = parse_url($url);
        $host      = strtolower($parsed['host'] ?? '');
        $isTwitter = in_array($host, self::TWITTER_HOSTS, true);

        $fetchUrl  = $isTwitter
            ? preg_replace('#^(https?://)(?:www\.)?(x|twitter)\.com#i', '$1fxtwitter.com', $url)
            : $url;

        $userAgent = $isTwitter
            ? 'Mozilla/5.0 (compatible; TalkBrowserBot/1.0)'
            : 'Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0';

        $ctx = stream_context_create([
            'http' => [
                'method'          => 'GET',
                'header'          => implode("\r\n", [
                    'Accept: ' . $accept,
                    'Accept-Language: en-US,en;q=0.5',
                    'User-Agent: ' . $userAgent,
                ]),
                'timeout'         => self::FETCH_TIMEOUT,
                'follow_location' => 1,
                'max_redirects'   => 5,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($fetchUrl, false, $ctx);
        if ($body === false || $body === '') {
            return null;
        }

        return $body;
    }

    /**
     * Fetch an image URL, enforce content-type and size limits.
     * Returns [bytes, mime] or [null, null].
     */
    private function fetchImage(string $url): array {
        $ctx = stream_context_create([
            'http' => [
                'method'          => 'GET',
                'header'          => implode("\r\n", [
                    'Accept: image/*',
                    'User-Agent: Mozilla/5.0 (compatible; NextcloudTalkBrowser/1.0)',
                ]),
                'timeout'         => self::FETCH_TIMEOUT,
                'follow_location' => 1,
                'max_redirects'   => 5,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $ctx);
        if ($body === false || $body === '') {
            return [null, null];
        }

        if (strlen($body) > self::MAX_IMAGE_BYTES) {
            return [null, null];
        }

        // Derive MIME from $http_response_header if available
        $mime = 'image/jpeg';
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $h) {
                if (stripos($h, 'Content-Type:') === 0) {
                    $parts = explode(':', $h, 2);
                    $mime  = strtolower(trim(explode(';', $parts[1])[0]));
                    break;
                }
            }
        }

        // Only proxy actual images
        if (!str_starts_with($mime, 'image/')) {
            return [null, null];
        }

        return [$body, $mime];
    }

    // -------------------------------------------------------------------------
    // OG image extraction
    // -------------------------------------------------------------------------

    private function extractOgImage(string $html, string $pageUrl): ?string {
        // Try og:image first, then twitter:image as fallback
        $patterns = [
            '/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i',
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\'][^>]*>/i',
            '/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i',
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']twitter:image["\'][^>]*>/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $m)) {
                $src = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5);
                if ($src === '') {
                    continue;
                }
                return $this->resolveUrl($src, $pageUrl);
            }
        }

        return null;
    }

    /**
     * Resolve a potentially relative OG image URL against the page's base URL.
     */
    private function resolveUrl(string $src, string $base): ?string {
        $parsed = parse_url($src);

        // Already absolute
        if (isset($parsed['scheme'])) {
            if (!in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
                return null;
            }
            return $src;
        }

        $baseParts = parse_url($base);
        if (!isset($baseParts['scheme'], $baseParts['host'])) {
            return null;
        }

        $origin = $baseParts['scheme'] . '://' . $baseParts['host'];
        if (isset($baseParts['port'])) {
            $origin .= ':' . $baseParts['port'];
        }

        // Protocol-relative //host/path
        if (str_starts_with($src, '//')) {
            return $baseParts['scheme'] . ':' . $src;
        }

        // Absolute path /foo/bar
        if (str_starts_with($src, '/')) {
            return $origin . $src;
        }

        // Relative path
        $basePath = isset($baseParts['path']) ? dirname($baseParts['path']) : '/';
        return $origin . rtrim($basePath, '/') . '/' . ltrim($src, '/');
    }

    // -------------------------------------------------------------------------
    // Response helpers
    // -------------------------------------------------------------------------

    private function cacheAndReturn(string $key, string $data, string $mime): Response {
        $this->writeCache($key, $data, $mime);
        if ($data === '' || $mime === '') {
            return $this->emptyResponse();
        }
        return $this->imageResponse($data, $mime);
    }

    private function imageResponse(string $data, string $mime): Response {
        if ($data === '' || $mime === '') {
            return $this->emptyResponse();
        }
        $response = new DataDisplayResponse($data, Http::STATUS_OK, [
            'Content-Type'  => $mime,
            'Cache-Control' => 'public, max-age=' . self::CACHE_TTL,
        ]);
        return $response;
    }

    private function emptyResponse(): Response {
        $response = new Response();
        $response->setStatus(Http::STATUS_NO_CONTENT);
        return $response;
    }
}
