<?php

declare(strict_types=1);

namespace OCA\TalkContentBrowser\Controller;

use OCA\TalkContentBrowser\AppInfo\Application;
use OCA\TalkContentBrowser\Service\UrlValidator;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\Files\IAppData;
use OCP\Files\AppData\IAppDataFactory;
use OCP\IRequest;

if (class_exists(__NAMESPACE__ . '\\OgMetaController', false)) {
    return;
}

/**
 * Server-side proxy that extracts and caches OpenGraph title + description
 * for external URLs.
 *
 * Returns JSON: { "title": string|null, "description": string|null }
 *
 * Flow:
 *   1. Receive ?url=<encoded-external-url>
 *   2. Check disk cache (APP_DATA/og-meta-cache/<hash>.json); serve if fresh
 *   3. Fetch page HTML, extract og:title / og:description (with twitter: fallbacks)
 *   4. Store to cache with TTL = 7 days
 *   5. Return JSON
 */
class OgMetaController extends Controller {
    private const CACHE_TTL       = 604800; // 7 days
    private const CACHE_TTL_EMPTY = 3600;   // 1 hour for "no meta found" sentinels
    private const FETCH_TIMEOUT   = 10;     // seconds
    private const MAX_HTML_BYTES  = 2 * 1024 * 1024; // 2 MiB — YouTube injects OG tags ~600 KiB in

    private IAppData $appData;
    private UrlValidator $urlValidator;

    public function __construct(
        IRequest $request,
        IAppDataFactory $appDataFactory,
        UrlValidator $urlValidator,
    ) {
        parent::__construct(Application::APP_ID, $request);
        $this->appData = $appDataFactory->get(Application::APP_ID);
        $this->urlValidator = $urlValidator;
    }

    #[NoAdminRequired]
    public function proxy(): JSONResponse {
        $rawUrl = $this->request->getParam('url', '');

        // --- Validate input URL ---
        if ($rawUrl === '') {
            return $this->emptyMeta();
        }

        $parsed = parse_url($rawUrl);
        if (
            !isset($parsed['scheme'], $parsed['host'])
            || !in_array(strtolower($parsed['scheme']), ['http', 'https'], true)
        ) {
            return $this->emptyMeta();
        }

        // --- SSRF protection: reject private/reserved IP targets ---
        if (!$this->urlValidator->validateExternalUrl($rawUrl)) {
            return $this->emptyMeta();
        }

        $cacheKey = hash('sha256', $rawUrl);

        // --- Try cache ---
        $cached = $this->readCache($cacheKey);
        if ($cached !== null) {
            return $this->metaResponse($cached['title'], $cached['description']);
        }

        // --- Fetch remote page HTML ---
        $html = $this->fetchUrl($rawUrl);
        if ($html === null) {
            $this->writeCache($cacheKey, null, null);
            return $this->emptyMeta();
        }

        // --- Extract OG / twitter meta ---
        $title       = $this->extractOgField($html, ['og:title', 'twitter:title'])
                    ?? $this->extractHtmlTitle($html);
        $description = $this->extractOgField($html, ['og:description', 'twitter:description']);

        $this->writeCache($cacheKey, $title, $description);
        return $this->metaResponse($title, $description);
    }

    // -------------------------------------------------------------------------
    // Cache helpers
    // -------------------------------------------------------------------------

    private function readCache(string $key): ?array {
        try {
            $folder   = $this->getCacheFolder();
            $jsonFile = $folder->getFile($key . '.json');
            $data     = json_decode($jsonFile->getContent(), true);

            if (!is_array($data) || !array_key_exists('ts', $data)) {
                return null;
            }

            $isEmpty = ($data['title'] === null && $data['description'] === null);
            $ttl     = $isEmpty ? self::CACHE_TTL_EMPTY : self::CACHE_TTL;

            if (time() - $data['ts'] > $ttl) {
                return null;
            }

            return ['title' => $data['title'], 'description' => $data['description']];
        } catch (\Throwable) {
            return null;
        }
    }

    private function writeCache(string $key, ?string $title, ?string $description): void {
        try {
            $folder  = $this->getCacheFolder();
            $content = json_encode([
                'ts'          => time(),
                'title'       => $title,
                'description' => $description,
            ]);

            try {
                $folder->getFile($key . '.json')->putContent($content);
            } catch (\OCP\Files\NotFoundException) {
                $folder->newFile($key . '.json', $content);
            }
        } catch (\Throwable) {
            // Cache write failure is non-fatal
        }
    }

    private function getCacheFolder(): \OCP\Files\SimpleFS\ISimpleFolder {
        try {
            return $this->appData->getFolder('og-meta-cache');
        } catch (\OCP\Files\NotFoundException) {
            return $this->appData->newFolder('og-meta-cache');
        }
    }

    // -------------------------------------------------------------------------
    // HTTP fetch helper
    // -------------------------------------------------------------------------

    /**
     * X.com (Twitter) is a JavaScript SPA that serves no OG tags to crawlers.
     * fxtwitter.com is a well-known open-source proxy that re-serves tweets with
     * proper OG meta tags — but only when accessed with a non-browser User-Agent.
     * We rewrite x.com / twitter.com fetch URLs to fxtwitter.com and use a plain
     * bot UA so fxtwitter returns its server-rendered embed page instead of
     * redirecting back to the SPA.
     */
    private const TWITTER_HOSTS = ['x.com', 'www.x.com', 'twitter.com', 'www.twitter.com'];

    private function fetchUrl(string $url): ?string {
        $parsed    = parse_url($url);
        $host      = strtolower($parsed['host'] ?? '');
        $isTwitter = in_array($host, self::TWITTER_HOSTS, true);

        $fetchUrl  = $isTwitter
            ? preg_replace('#^(https?://)(?:www\.)?(x|twitter)\.com#i', '$1fxtwitter.com', $url)
            : $url;

        $userAgent = $isTwitter
            ? 'Mozilla/5.0 (compatible; TalkBrowserBot/1.0)'  // fxtwitter needs a non-browser UA
            : 'Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0';

        // SSRF protection: validate the final URL (post-rewrite) before fetching.
        if (!$this->urlValidator->validateExternalUrl($fetchUrl)) {
            return null;
        }

        $ctx = stream_context_create([
            'http' => [
                'method'          => 'GET',
                'header'          => implode("\r\n", [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
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

        return substr($body, 0, self::MAX_HTML_BYTES);
    }

    // -------------------------------------------------------------------------
    // OG / twitter meta extraction
    // -------------------------------------------------------------------------

    /**
     * Extract the content of the first matching meta property/name tag.
     * Supports both attribute orderings: property/name before content, and vice-versa.
     *
     * @param string[] $fields  e.g. ['og:title', 'twitter:title']
     */
    private function extractOgField(string $html, array $fields): ?string {
        foreach ($fields as $field) {
            // Determine the attribute name: og:* use property=, twitter:* use name=
            $attr = str_starts_with($field, 'twitter:') ? 'name' : 'property';

            $patterns = [
                // <meta property="og:title" content="…">
                '/<meta[^>]+' . $attr . '=["\']' . preg_quote($field, '/') . '["\'][^>]+content=["\']([^"\']*)["\'][^>]*>/i',
                // <meta content="…" property="og:title">
                '/<meta[^>]+content=["\']([^"\']*)["\'][^>]+' . $attr . '=["\']' . preg_quote($field, '/') . '["\'][^>]*>/i',
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $html, $m)) {
                    $value = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5);
                    if ($value !== '') {
                        return $this->truncate($value, 300);
                    }
                }
            }
        }

        return null;
    }

    /**
     * Fall back to the plain <title> element when no OG/twitter title is found.
     */
    private function extractHtmlTitle(string $html): ?string {
        if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $m)) {
            $value = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5);
            if ($value !== '') {
                return $this->truncate($value, 300);
            }
        }
        return null;
    }

    private function truncate(string $text, int $max): string {
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        return mb_substr($text, 0, $max - 1) . '…';
    }

    // -------------------------------------------------------------------------
    // Response helpers
    // -------------------------------------------------------------------------

    private function metaResponse(?string $title, ?string $description): JSONResponse {
        return new JSONResponse(
            ['title' => $title, 'description' => $description],
            Http::STATUS_OK
        );
    }

    private function emptyMeta(): JSONResponse {
        return new JSONResponse(
            ['title' => null, 'description' => null],
            Http::STATUS_OK
        );
    }
}
