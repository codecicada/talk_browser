const path = require('path')

const webpackConfig = require('@nextcloud/webpack-vue-config')

// Entry point — @nextcloud/webpack-vue-config names output as ${appName}-[name].js
// so 'main' produces talk_content_browser-main.js, matching addScript() in main.php
webpackConfig.entry = {
	main: path.join(__dirname, 'src', 'main.js'),
}

// @nextcloud/vue@8 targets Vue 2.7. However @vueuse/core (a dependency of @nextcloud/vue)
// ships its own nested vue-demi that may be in "Vue 3 mode" depending on install order.
// We force ALL vue-demi resolutions to the Vue 2.7 shim so that exports like
// Fragment / TransitionGroup don't leak Vue 3 stubs into a Vue 2 build.
if (!webpackConfig.resolve) webpackConfig.resolve = {}
if (!webpackConfig.resolve.alias) webpackConfig.resolve.alias = {}

webpackConfig.resolve.alias['vue-demi'] = path.resolve(
	__dirname,
	'node_modules/@vueuse/core/node_modules/vue-demi/lib/v2.7/index.mjs',
)

// Also make sure all 'vue' imports resolve to the same Vue 2.7 instance,
// preventing duplicate Vue instances from @nextcloud/vue's own dependencies.
webpackConfig.resolve.alias['vue'] = path.resolve(
	__dirname,
	'node_modules/vue/dist/vue.runtime.esm.js',
)

module.exports = webpackConfig
