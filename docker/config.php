<?php
$CONFIG = array(
    'debug' => true,
    'loglevel' => 0,
    'overwriteprotocol' => 'http',
    'apps_paths' => array(
        array(
            'path' => '/var/www/html/apps',
            'url' => '/apps',
            'writable' => false,
        ),
        array(
            'path' => '/apps-dev',
            'url' => '/apps-dev',
            'writable' => true,
        ),
    ),
);
