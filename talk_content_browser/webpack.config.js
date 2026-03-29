const { VueLoaderPlugin } = require('vue-loader')
const path = require('path')

const webpackConfig = require('@nextcloud/webpack-vue-config')

// The @nextcloud/webpack-vue-config already sets up Vue, CSS, assets, etc.
// We only need to override the entry point to match our app ID naming convention.
webpackConfig.entry = {
	'talk_content_browser-main': path.join(__dirname, 'src', 'main.js'),
}

module.exports = webpackConfig
