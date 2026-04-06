<template>
	<div class="error-boundary">
		<!-- Fallback UI shown when a descendant throws a render/lifecycle error -->
		<NcEmptyContent
			v-if="hasError"
			:name="t('talk_browser', 'Something went wrong')"
			:description="t('talk_browser', 'An unexpected error occurred. You can try to recover by clicking Retry.')"
		>
			<template #icon>
				<span class="icon-error" aria-hidden="true" />
			</template>
			<template #action>
				<NcButton @click="retry">
					{{ t('talk_browser', 'Retry') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<!-- Normal slot content, keyed so retry forces a full re-mount -->
		<div v-else :key="renderKey">
			<slot />
		</div>
	</div>
</template>

<script>
import { NcButton, NcEmptyContent } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'ErrorBoundary',

	components: {
		NcButton,
		NcEmptyContent,
	},

	data() {
		return {
			hasError: false,
			renderKey: 0,
		}
	},

	errorCaptured(err, vm, info) {
		// eslint-disable-next-line no-console
		console.error('[talk_browser] ErrorBoundary caught error:', err, '\nComponent:', vm, '\nInfo:', info)
		this.hasError = true
		// Return false to stop the error from propagating further up the tree
		return false
	},

	methods: {
		t,
		retry() {
			this.hasError = false
			this.renderKey++
		},
	},
}
</script>

<style scoped>
.error-boundary {
	width: 100%;
	height: 100%;
}
</style>
