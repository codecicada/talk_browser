<?php
/**
 * Main template shell — loads the compiled Vue 3 app.
 * The Vue app mounts itself onto #talk-content-browser-app.
 */
\OCP\Util::addScript('talk_content_browser', 'talk_content_browser-main');
\OCP\Util::addStyle('talk_content_browser', 'talk_content_browser-main');
?>
<div id="talk-content-browser-app"></div>
