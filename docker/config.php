<?php
/**
 * Custom Nextcloud configuration for local development.
 * This file is automatically loaded alongside config.php.
 */
$CONFIG = [
    // Enable debug mode: disables JS/CSS caching so webpack watch changes are instant
    'debug' => true,

    // Allow the apps-extra directory alongside the built-in apps directory
    'apps_paths' => [
        [
            'path'     => '/var/www/html/apps',
            'url'      => '/apps',
            'writable' => false,
        ],
        [
            'path'     => '/var/www/html/apps-extra',
            'url'      => '/apps-extra',
            'writable' => false,
        ],
    ],

    // Log everything in dev
    'loglevel' => 0,

    // Allow local network origins for CORS during dev
    'overwriteprotocol' => 'http',
];
