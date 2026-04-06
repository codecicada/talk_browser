<?php

declare(strict_types=1);

/**
 * Minimal OCP\* and OCA\* stubs for testing without the Nextcloud AppFramework.
 * Uses the bracketed namespace syntax to allow multiple namespaces in one file.
 */

namespace OCP\AppFramework {
    if (!class_exists(\OCP\AppFramework\Controller::class)) {
        class Controller {
            public function __construct(string $appName, \OCP\IRequest $request) {}
        }
    }
    if (!class_exists(\OCP\AppFramework\App::class)) {
        class App {
            public function __construct(string $appName) {}
        }
    }
    if (!class_exists(\OCP\AppFramework\Http::class)) {
        class Http {
            const STATUS_OK         = 200;
            const STATUS_NO_CONTENT = 204;
        }
    }
}

namespace OCP\AppFramework\Http {
    if (!class_exists(\OCP\AppFramework\Http\JSONResponse::class)) {
        class JSONResponse {}
    }
    if (!class_exists(\OCP\AppFramework\Http\DataDisplayResponse::class)) {
        class DataDisplayResponse {
            public function __construct(string $data, int $status, array $headers = []) {}
        }
    }
    if (!class_exists(\OCP\AppFramework\Http\Response::class)) {
        class Response {
            public function setStatus(int $code): void {}
        }
    }
    if (!class_exists(\OCP\AppFramework\Http\TemplateResponse::class)) {
        class TemplateResponse {}
    }
}

namespace OCP\AppFramework\Http\Attribute {
    if (!class_exists(\OCP\AppFramework\Http\Attribute\NoAdminRequired::class)) {
        #[\Attribute] class NoAdminRequired {}
    }
    if (!class_exists(\OCP\AppFramework\Http\Attribute\NoCSRFRequired::class)) {
        #[\Attribute] class NoCSRFRequired {}
    }
}

namespace OCP\AppFramework\Services {
    if (!interface_exists(\OCP\AppFramework\Services\IInitialState::class)) {
        interface IInitialState {}
    }
}

namespace OCP\Files {
    if (!class_exists(\OCP\Files\NotFoundException::class)) {
        class NotFoundException extends \Exception {}
    }
    if (!interface_exists(\OCP\Files\IAppData::class)) {
        interface IAppData {
            public function getFolder(string $name): \OCP\Files\SimpleFS\ISimpleFolder;
            public function newFolder(string $name): \OCP\Files\SimpleFS\ISimpleFolder;
        }
    }
}

namespace OCP\Files\AppData {
    if (!interface_exists(\OCP\Files\AppData\IAppDataFactory::class)) {
        interface IAppDataFactory {
            public function get(string $appId): \OCP\Files\IAppData;
        }
    }
}

namespace OCP\Files\SimpleFS {
    if (!interface_exists(\OCP\Files\SimpleFS\ISimpleFolder::class)) {
        interface ISimpleFolder {
            public function getFile(string $name): \OCP\Files\SimpleFS\ISimpleFile;
            public function newFile(string $name, string $content = ''): \OCP\Files\SimpleFS\ISimpleFile;
        }
    }
    if (!interface_exists(\OCP\Files\SimpleFS\ISimpleFile::class)) {
        interface ISimpleFile {
            public function getContent(): string;
            public function putContent(string $data): void;
        }
    }
}

namespace OCP {
    if (!interface_exists(\OCP\IRequest::class)) {
        interface IRequest {
            public function getParam(string $key, mixed $default = null): mixed;
        }
    }
    if (!interface_exists(\OCP\IUserSession::class)) {
        interface IUserSession {}
    }
    if (!class_exists(\OCP\Util::class)) {
        class Util {}
    }
}

namespace OCP\App {
    if (!interface_exists(\OCP\App\IAppManager::class)) {
        interface IAppManager {}
    }
}

namespace OCA\TalkContentBrowser\AppInfo {
    if (!class_exists(\OCA\TalkContentBrowser\AppInfo\Application::class)) {
        class Application {
            const APP_ID = 'talk_browser';
        }
    }
}

namespace OCA\TalkContentBrowser\Service {
    if (!class_exists(\OCA\TalkContentBrowser\Service\UrlValidator::class)) {
        class UrlValidator {
            public function validateExternalUrl(string $url): bool { return true; }
        }
    }
}
