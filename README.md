# Talk Content Browser

A Nextcloud app that lets you browse the content shared in any Talk conversation — grouped by type.

**Tabs:** Overview · Images & Video · Files · Audio · Voice notes · Links · Locations · Other

Each tab has a search field. Defaults to the **Note to Self** conversation on load.

---

## Requirements

- Docker & Docker Compose
- Node.js ≥ 20 + npm ≥ 9 (on the host, for frontend builds)

---

## Dev setup

### 1. Start Nextcloud

```bash
docker compose up -d
```

Nextcloud will be available at **http://localhost:8080** after ~60 s (first boot runs the installer).

Credentials: `admin` / `admin`

### 2. Install & enable Talk + the app (one-off)

```bash
docker compose exec nextcloud php occ app:install spreed
docker compose exec nextcloud php occ app:enable talk_content_browser
```

### 3. Start the frontend watcher

```bash
cd talk_content_browser
npm install
npm run watch
```

Webpack rebuilds `js/` on every save in `src/`. Because Nextcloud runs in debug mode (`'debug' => true` in `docker/config.php`), JS/CSS caching is disabled — a browser refresh picks up changes immediately.

### 4. Open the app

Navigate to **http://localhost:8080/apps/talk_content_browser**

The app also appears as an entry in the Nextcloud top navigation bar.

---

## Project structure

```
nexcloud-talk/
├── docker-compose.yml                  # Nextcloud 31 + MariaDB
├── docker/
│   └── config.php                      # debug mode, apps-extra path
└── talk_content_browser/               # the Nextcloud app
    ├── appinfo/
    │   ├── info.xml                    # app manifest (App Store metadata)
    │   └── routes.php                  # page route → PageController
    ├── lib/
    │   ├── AppInfo/Application.php     # bootstrapper (IBootstrap)
    │   └── Controller/PageController.php  # serves the SPA shell
    ├── templates/main.php              # PHP shell: loads compiled JS/CSS
    ├── img/app.svg                     # navigation icon
    ├── src/
    │   ├── main.js                     # Vue 3 entry point
    │   ├── App.vue                     # root component & state orchestration
    │   ├── constants.js                # tab definitions, conversation type enums
    │   ├── api/talk.js                 # Talk OCS REST API helpers
    │   ├── composables/
    │   │   ├── useConversations.js     # load conversations, default to Note to Self
    │   │   └── useSharedItems.js       # paginated shared items + link extraction
    │   └── components/
    │       ├── ConversationPicker.vue  # dropdown to switch conversations
    │       ├── ContentTabs.vue         # tab bar + per-tab search input
    │       ├── OverviewPanel.vue       # grouped summary of all content types
    │       ├── MediaGallery.vue        # image/video thumbnail grid
    │       ├── FileList.vue            # file list with mime icons & sizes
    │       ├── AudioList.vue           # HTML5 audio player per item (audio + voice)
    │       ├── LinkList.vue            # extracted URLs with favicons
    │       └── GenericList.vue         # locations, Deck cards, other rich objects
    ├── package.json
    ├── webpack.config.js               # @nextcloud/webpack-vue-config base
    └── .eslintrc.js
```

---

## Useful commands

| Task | Command |
|------|---------|
| Start containers | `docker compose up -d` |
| Stop containers | `docker compose down` |
| Destroy volumes (full reset) | `docker compose down -v` |
| Enable the app | `docker compose exec nextcloud php occ app:enable talk_content_browser` |
| Disable the app | `docker compose exec nextcloud php occ app:disable talk_content_browser` |
| View Nextcloud logs | `docker compose exec nextcloud php occ log:tail` |
| Production JS build | `cd talk_content_browser && npm run build` |
| Lint frontend | `cd talk_content_browser && npm run lint` |

---

## How it works

All Talk interaction is done **client-side** via the Talk OCS REST API. Because the Vue app runs inside an authenticated Nextcloud session, `@nextcloud/axios` injects the CSRF token automatically — no separate authentication is needed.

| Content type | API source |
|---|---|
| Overview | `GET /ocs/…/chat/{token}/share/overview` |
| Images & Video | `GET /ocs/…/chat/{token}/share?objectType=media` |
| Files | `GET /ocs/…/chat/{token}/share?objectType=file` |
| Audio | `GET /ocs/…/chat/{token}/share?objectType=audio` |
| Voice notes | `GET /ocs/…/chat/{token}/share?objectType=voice` |
| Locations | `GET /ocs/…/chat/{token}/share?objectType=location` |
| Other | `GET /ocs/…/chat/{token}/share?objectType=other` |
| **Links** | Full message history scan + client-side URL regex |

Pagination uses the `X-Chat-Last-Given` response header as a cursor. A `304 Not Modified` signals the end of history (used for link scanning).

---

## App Store publishing (when ready)

1. Update `appinfo/info.xml` — bump `<version>`, set your real author/email/repo URLs.
2. Build production assets: `npm ci && npm run build`
3. Strip dev files: `node_modules/`, `src/`, `package*.json`, `webpack.config.js`, `tests/`
4. Sign the app with your Nextcloud code-signing certificate.
5. Pack as `talk_content_browser.tar.gz` (root entry must be the folder name).
6. Create a GitHub Release, attach the tarball, publish at https://apps.nextcloud.com.
