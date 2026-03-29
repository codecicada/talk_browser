#!/bin/sh
# Re-apply apps.config.php on every restart so apps_paths always points to
# /apps-dev. We do NOT call occ here — occ requires a writable apps directory
# at bootstrap time, which is circular when reconfiguring apps_paths.
# /apps-dev permissions are fixed by the fix-perms init container in compose.
set -e

# Overwrite apps.config.php which loads AFTER config.php and would otherwise
# reset apps_paths back to the image defaults (custom_apps only).
cat > /var/www/html/config/apps.config.php << 'APPSCONFIG'
<?php
$CONFIG = array(
  'apps_paths' => array(
    array('path' => OC::$SERVERROOT.'/apps', 'url' => '/apps', 'writable' => false),
    array('path' => '/apps-dev', 'url' => '/apps-dev', 'writable' => true),
  ),
);
APPSCONFIG

echo "Config synced (apps.config.php updated)."
