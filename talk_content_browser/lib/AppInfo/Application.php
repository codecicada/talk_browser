<?php

declare(strict_types=1);

namespace OCA\TalkContentBrowser\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
    public const APP_ID = 'talk_content_browser';

    public function __construct() {
        parent::__construct(self::APP_ID);
    }

    public function register(IRegistrationContext $context): void {
        // Nothing to register at boot time for this app.
        // All Talk interaction is done client-side via the Talk OCS REST API.
    }

    public function boot(IBootContext $context): void {
        // Nothing required at boot time.
    }
}
