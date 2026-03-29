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
        $user = $this->userSession->getUser();

        // Pass minimal bootstrap data to the Vue app via initial state
        $this->initialState->provideInitialState('user-id', $user?->getUID() ?? '');
        $this->initialState->provideInitialState('user-display-name', $user?->getDisplayName() ?? '');

        return new TemplateResponse(Application::APP_ID, 'main');
    }
}
