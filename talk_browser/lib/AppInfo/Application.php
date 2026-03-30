<?php

declare(strict_types=1);

namespace OCA\TalkContentBrowser\AppInfo;

use OCP\AppFramework\App;

// Guard against double-inclusion that can occur during certain Nextcloud
// bootstrap sequences (e.g. occ with multiple app load phases).
if (class_exists(__NAMESPACE__ . '\\Application', false)) {
    return;
}

class Application extends App {
    public const APP_ID = 'talk_browser';

    public function __construct() {
        parent::__construct(self::APP_ID);
    }
}
