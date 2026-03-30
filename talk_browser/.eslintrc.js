module.exports = {
	root: true,
	extends: [
		'@nextcloud/eslint-config/vue3',
	],
	rules: {
		// Allow console during development
		'no-console': process.env.NODE_ENV === 'production' ? 'warn' : 'off',
	},
}
