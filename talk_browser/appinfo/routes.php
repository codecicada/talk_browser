<?php

declare(strict_types=1);

return [
    'routes' => [
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
