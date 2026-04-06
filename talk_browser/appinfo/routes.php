<?php

declare(strict_types=1);

// Talk tokens are lowercase alphanumeric strings (typically 8–12 chars).
// The requirement below ensures {token} and {tab} never match reserved words
// like "api" used by our own internal endpoints.
$tokenPattern = '[a-z0-9]+';
// IMPORTANT: Keep in sync with TABS[].id in src/constants.js
$tabPattern   = 'overview|media|file|audio|voice|links|location|other';

return [
    'routes' => [
        // OG image proxy — lives under /api/ which is excluded from {token} by requirement below
        [
            'name'         => 'og_image#proxy',
            'url'          => '/api/og-image',
            'verb'         => 'GET',
        ],
        // OG metadata proxy (title + description) — JSON response
        [
            'name'         => 'og_meta#proxy',
            'url'          => '/api/og-meta',
            'verb'         => 'GET',
        ],
        // SPA root
        [
            'name' => 'page#index',
            'url'  => '/',
            'verb' => 'GET',
        ],
        // /apps/talk_browser/{token}
        [
            'name'         => 'page#token',
            'url'          => '/{token}',
            'verb'         => 'GET',
            'requirements' => ['token' => $tokenPattern],
        ],
        // /apps/talk_browser/{token}/{tab}
        [
            'name'         => 'page#token_tab',
            'url'          => '/{token}/{tab}',
            'verb'         => 'GET',
            'requirements' => ['token' => $tokenPattern, 'tab' => $tabPattern],
        ],
    ],
];
