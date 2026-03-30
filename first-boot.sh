#!/bin/sh
# first-boot.sh — Run after `docker compose up -d` on a fresh volume to:
#   1. Wait for Nextcloud to finish its first-boot install
#   2. Install and enable Nextcloud Talk (spreed)
#   3. Enable the talk_browser app
#
# Only needed once per `docker compose down -v` cycle.
# Usage: ./first-boot.sh

set -e

CONTAINER="nextcloud_app"
OCC="docker exec -u 33 $CONTAINER php /var/www/html/occ"

echo "Waiting for Nextcloud to finish installing..."
for i in $(seq 1 30); do
  STATUS=$($OCC status --output=json 2>/dev/null \
    | python3 -c "import sys,json; print(json.load(sys.stdin).get('installed','?'))" 2>/dev/null \
    || echo "not_ready")
  printf "  [%d/30] installed=%s\n" "$i" "$STATUS"
  [ "$STATUS" = "True" ] && break
  sleep 10
done

if [ "$STATUS" != "True" ]; then
  echo "Nextcloud did not install in time. Check: docker logs $CONTAINER"
  exit 1
fi

echo ""
echo "Installing Nextcloud Talk (spreed)..."
$OCC app:install spreed 2>/dev/null || $OCC app:enable spreed 2>/dev/null || true

echo ""
echo "Enabling talk_browser..."
$OCC app:enable talk_browser

echo ""
echo "All done! Open http://localhost:8080 and log in as admin/admin."
echo "Navigate to the Talk Browser icon in the top nav."
