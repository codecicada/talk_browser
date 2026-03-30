<?php
/**
 * Main template shell — loads the compiled Vue app.
 * The Vue app mounts itself onto #talk-browser-app.
 */
\OCP\Util::addScript('talk_browser', 'talk_browser-main');
?>
<div id="talk-browser-app"></div>
