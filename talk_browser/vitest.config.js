import { defineConfig } from 'vitest/config'

export default defineConfig({
	test: {
		include: ['tests/js/**/*.test.js'],
		environment: 'node',
		globals: true,
	},
	resolve: {
		alias: {
			// Vue 2.7 ships its own composition API — alias vue-demi to it
			'vue-demi': 'vue/dist/vue.runtime.common.js',
		},
	},
})
