import Vue from 'vue'
import App from './App.vue'
import './assets/utilities.css'

Vue.config.errorHandler = function(err, vm, info) {
	// eslint-disable-next-line no-console
	console.error('[talk_browser] Uncaught Vue error:', err, '\nComponent:', vm, '\nInfo:', info)
}

new Vue({
	el: '#talk-browser-app',
	render: h => h(App),
})
