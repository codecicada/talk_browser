#!/bin/sh
# Configure debug mode, logging, and apps_paths after a fresh install.
set -e
php /var/www/html/occ config:system:set debug --value=true --type=boolean
php /var/www/html/occ config:system:set loglevel --value=0 --type=integer
php /var/www/html/occ config:system:set overwriteprotocol --value=http

# Do NOT use occ for apps_paths — occ merges the value with the existing
# apps.config.php entries instead of replacing, producing a corrupted array.
# Instead, overwrite apps.config.php directly. It loads after config.php,
# so it always wins for apps_paths.
cat > /var/www/html/config/apps.config.php << 'APPSCONFIG'
<?php
$CONFIG = array(
  'apps_paths' => array(
    array('path' => OC::$SERVERROOT.'/apps', 'url' => '/apps', 'writable' => false),
    array('path' => '/apps-dev', 'url' => '/apps-dev', 'writable' => true),
  ),
);
APPSCONFIG

# Remove any stale apps_paths entries that occ may have written to config.php
# on previous runs (they would conflict with apps.config.php).
php /var/www/html/occ config:system:delete apps_paths || true

echo "Post-install config applied."
