<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap for talk_browser unit tests.
 *
 * Loads the Composer autoloader, then defines minimal OCP\* stubs so the
 * controller files can be required without the full Nextcloud bootstrap.
 * Each namespace block must be the only statement at file scope — we use
 * separate included files to work around the "one namespace per file" rule
 * and keep things clean.
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/stubs.php';
