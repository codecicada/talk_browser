#!/bin/sh
# sync-app.sh — Copy host source files into the nextcloud_app container's
# named volume (/apps-dev/talk_browser).
#
# Run this after any PHP/template/config change to see it reflected in
# the running Nextcloud instance. Vue changes also require a `npm run build`
# first (output goes into talk_browser/js/).
#
# Usage: ./sync-app.sh

set -e

APP_DIR="$(dirname "$0")/talk_browser"
CONTAINER="nextcloud_app"
DEST="/apps-dev/talk_browser"

if ! docker ps --format '{{.Names}}' | grep -q "^${CONTAINER}$"; then
  echo "Error: container '$CONTAINER' is not running. Start it with: docker compose up -d"
  exit 1
fi

echo "Syncing $APP_DIR → $CONTAINER:$DEST ..."

# Sync all source directories (PHP, templates, config, compiled JS/CSS, l10n)
# Use "/dir/." notation to copy directory *contents* into the destination
for dir in appinfo lib templates img js css l10n; do
  if [ -d "$APP_DIR/$dir" ]; then
    docker cp "$APP_DIR/$dir/." "$CONTAINER:$DEST/$dir/"
  fi
done

# Optionally clear Nextcloud's app info cache so info.xml changes are picked up
docker exec -u 33 "$CONTAINER" php /var/www/html/occ maintenance:repair --quiet 2>/dev/null || true

echo "Done. Refresh your browser (Ctrl+Shift+R to bypass cache)."
