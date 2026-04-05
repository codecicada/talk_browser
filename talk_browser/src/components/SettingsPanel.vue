<template>
	<div class="settings-panel">
		<!-- View section -->
		<div class="settings-panel__section">
			<h3 class="settings-panel__section-title">
				{{ t('talk_browser', 'View') }}
			</h3>
			<NcCheckboxRadioSwitch :checked="!hideGroupConversations"
				@update:checked="onToggleGroupConversations">
				{{ t('talk_browser', 'Show group conversations') }}
			</NcCheckboxRadioSwitch>
		</div>

		<!-- About section -->
		<div class="settings-panel__section">
			<h3 class="settings-panel__section-title">
				{{ t('talk_browser', 'About') }}
			</h3>
			<dl class="settings-panel__about-list">
				<dt>{{ t('talk_browser', 'Version') }}</dt>
				<dd>{{ appVersion }}</dd>
				<dt>{{ t('talk_browser', 'Licence') }}</dt>
				<dd>{{ appLicence }}</dd>
			</dl>
			<a href="https://github.com/codecicada/talk_browser"
				target="_blank"
				rel="noopener noreferrer"
				class="settings-panel__repo-link">
				{{ t('talk_browser', 'View on GitHub') }}
			</a>
		</div>
	</div>
</template>

<script>
import { NcCheckboxRadioSwitch } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import { loadState } from '@nextcloud/initial-state'

export default {
	name: 'SettingsPanel',

	components: {
		NcCheckboxRadioSwitch,
	},

	props: {
		hideGroupConversations: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['update:hide-group-conversations'],

	data() {
		let appVersion = ''
		let appLicence = ''
		try {
			appVersion = loadState('talk_browser', 'app-version', '')
			appLicence = loadState('talk_browser', 'app-licence', '')
		} catch (e) {
			// loadState throws if key is missing; use defaults
		}
		return {
			appVersion,
			appLicence,
		}
	},

	methods: {
		t,

		onToggleGroupConversations(checked) {
			// checked = true means "show group conversations" → hideGroupConversations = false
			this.$emit('update:hide-group-conversations', !checked)
		},
	},
}
</script>

<style scoped>
.settings-panel {
	padding: 8px 0;
}

.settings-panel__section {
	padding: 8px 16px 12px;
}

.settings-panel__section + .settings-panel__section {
	border-top: 1px solid var(--color-border);
}

.settings-panel__section-title {
	font-size: 13px;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
	text-transform: uppercase;
	letter-spacing: 0.04em;
	margin: 0 0 8px;
}

.settings-panel__about-list {
	display: grid;
	grid-template-columns: auto 1fr;
	gap: 2px 12px;
	margin: 0 0 8px;
	font-size: 13px;
}

.settings-panel__about-list dt {
	color: var(--color-text-maxcontrast);
	white-space: nowrap;
}

.settings-panel__about-list dd {
	margin: 0;
	color: var(--color-main-text);
}

.settings-panel__repo-link {
	font-size: 13px;
	color: var(--color-primary-element);
	text-decoration: none;
}

.settings-panel__repo-link:hover {
	text-decoration: underline;
}
</style>
