# Talk Browser

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

### 2. First-boot setup (one-off after each `docker compose down -v`)

```bash
./first-boot.sh
```

This script waits for Nextcloud to finish installing, then installs Talk (spreed) and enables the `talk_browser` app.

> **Important:** `spreed` must be installed **before** `talk_browser` is enabled, or the Talk API will not be available. `first-boot.sh` handles the correct order automatically.

### 3. Build the frontend

```bash
cd talk_browser
npm install
npm run watch   # or: npm run build for a one-shot production build
```

Webpack rebuilds `js/` on every save in `src/`. After building, sync the compiled output into the running container:

```bash
./sync-app.sh
```

Then hard-refresh the browser (Ctrl+Shift+R).

### 4. Open the app

Navigate to **http://localhost:8080/apps/talk_browser**

The app also appears as an entry in the Nextcloud top navigation bar.

---

## Syncing changes

Because the app is served from a Docker **named volume** (not a direct bind mount), changes to PHP files, templates, or `info.xml` on the host are **not** automatically reflected in the container. Run:

```bash
./sync-app.sh
```

This copies `appinfo/`, `lib/`, `templates/`, `img/`, `js/`, and `css/` into the container.

---

## Project structure

```
nexcloud-talk/
├── docker-compose.yml                  # Nextcloud 31 + MariaDB + fix-perms init container
├── docker/
│   ├── apache/
│   │   └── apps-dev.conf               # Apache alias for /apps-dev static asset serving
│   └── hooks/
│       ├── post-installation/          # runs once on first boot (occ config + apps.config.php)
│       └── before-starting/            # runs on every start (re-applies apps.config.php)
├── first-boot.sh                       # one-off setup: install Talk + enable app
├── sync-app.sh                         # copy host source into container named volume
└── talk_browser/                       # the Nextcloud app
    ├── appinfo/
    │   ├── info.xml                    # app manifest
    │   └── routes.php                  # page route → PageController
    ├── lib/
    │   ├── AppInfo/Application.php     # app bootstrapper
    │   └── Controller/PageController.php  # serves the SPA shell
    ├── templates/main.php              # PHP shell: loads compiled JS
    ├── img/app.svg                     # navigation icon
    ├── src/
    │   ├── main.js                     # Vue 2.7 entry point
    │   ├── App.vue                     # root component & state orchestration
    │   ├── constants.js                # tab definitions, conversation type enums
    │   ├── api/talk.js                 # Talk OCS REST API helpers
    │   ├── composables/
    │   │   ├── useConversations.js     # load conversations, default to Note to Self
    │   │   └── useSharedItems.js       # paginated shared items + link extraction
    │   └── components/
    │       ├── ConversationPicker.vue  # sidebar conversation list
    │       ├── ContentTabs.vue         # tab bar + per-tab search input
    │       ├── OverviewPanel.vue       # grouped summary of all content types
    │       ├── MediaGallery.vue        # image/video thumbnail grid
    │       ├── FileList.vue            # file list with mime icons & sizes
    │       ├── AudioList.vue           # HTML5 audio player per item (audio + voice)
    │       ├── LinkList.vue            # extracted URLs from message history
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
| First-boot setup | `./first-boot.sh` |
| Sync changes | `./sync-app.sh` |
| Stop containers | `docker compose down` |
| Destroy volumes (full reset) | `docker compose down -v` |
| Run occ | `docker exec -u 33 nextcloud_app php /var/www/html/occ <cmd>` |
| View Nextcloud logs | `docker exec -u 33 nextcloud_app php /var/www/html/occ log:tail` |
| Watch frontend | `cd talk_browser && npm run watch` |
| Production JS build | `cd talk_browser && npm run build` |
| Lint frontend | `cd talk_browser && npm run lint` |

---

## Architecture notes

### Named volume for `/apps-dev`

The Nextcloud Docker image populates `/var/www/html` via `rsync` on first boot. Mounting a bind mount inside that path causes rsync to fail. The app is therefore mounted outside at `/apps-dev`.

Nextcloud requires the writable apps path to be owned by `www-data` (uid 33). A bind mount from the host is always owned by `root:root` inside the container. To fix this, a one-shot `fix-perms` init container runs `chown 33:33 /apps-dev` before Nextcloud starts, writing to a **named volume** shared with the `nextcloud` service.

The trade-off: host file changes are not automatically visible inside the container. Use `./sync-app.sh` to push changes.

### Apache alias for static assets

The app lives at `/apps-dev/` which is outside Apache's DocumentRoot (`/var/www/html`). A static config file (`docker/apache/apps-dev.conf`) is bind-mounted directly into `/etc/apache2/conf-enabled/` so Apache can serve JS/CSS from that path on every restart without requiring root access from a hook script.

### `apps.config.php` override

Nextcloud ships a bundled `apps.config.php` that hardcodes `apps_paths` to `[apps, custom_apps]`. This file loads **after** `config.php` and wins for array keys. The `before-starting` Docker hook overwrites it on every container start to include `/apps-dev` as a writable apps path.

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

Pagination uses the `X-Chat-Last-Given` response header as a cursor.

---

## App Store publishing (when ready)

1. Update `appinfo/info.xml` — bump `<version>`, set real author/email/repo URLs.
2. Build production assets: `cd talk_browser && npm ci && npm run build`
3. Strip dev files: `node_modules/`, `src/`, `package*.json`, `webpack.config.js`
4. Sign the app with your Nextcloud code-signing certificate.
5. Pack as `talk_browser.tar.gz` (root entry must be the folder name).
6. Create a GitHub Release, attach the tarball, publish at https://apps.nextcloud.com.
