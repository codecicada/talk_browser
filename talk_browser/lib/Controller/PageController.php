<?php

declare(strict_types=1);

namespace OCA\TalkContentBrowser\Controller;

use OCA\TalkContentBrowser\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;
use OCP\IUserSession;

// Guard against double-inclusion during certain Nextcloud bootstrap sequences.
if (class_exists(__NAMESPACE__ . '\\PageController', false)) {
    return;
}

class PageController extends Controller {
    public function __construct(
        IRequest $request,
        private readonly IInitialState $initialState,
        private readonly IUserSession $userSession,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Serve the main SPA shell.
     * All Talk API calls are made client-side by Vue.
     */
    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function index(): TemplateResponse {
        return $this->serveSpa();
    }

    /** /apps/talk_browser/{token} */
    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function token(string $token): TemplateResponse {
        return $this->serveSpa();
    }

    /** /apps/talk_browser/{token}/{tab} */
    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function tokenTab(string $token, string $tab): TemplateResponse {
        return $this->serveSpa();
    }

    private function serveSpa(): TemplateResponse {
        $user = $this->userSession->getUser();

        // Pass minimal bootstrap data to the Vue app via initial state
        $this->initialState->provideInitialState('user-id', $user?->getUID() ?? '');
        $this->initialState->provideInitialState('user-display-name', $user?->getDisplayName() ?? '');

        return new TemplateResponse(Application::APP_ID, 'main');
    }
}
