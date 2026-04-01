<?php

declare(strict_types=1);

return [
    'routes' => [
        // OG image proxy — prefixed with /api/ so it never collides with /{token} SPA routes
        [
            'name' => 'og_image#proxy',
            'url'  => '/api/og-image',
            'verb' => 'GET',
        ],
        // SPA root
        [
            'name' => 'page#index',
            'url'  => '/',
            'verb' => 'GET',
        ],
        // /apps/talk_browser/{token}
        [
            'name' => 'page#token',
            'url'  => '/{token}',
            'verb' => 'GET',
        ],
        // /apps/talk_browser/{token}/{tab}
        [
            'name' => 'page#token_tab',
            'url'  => '/{token}/{tab}',
            'verb' => 'GET',
        ],
    ],
];
