<template>
	<div class="content-tabs">
		<!-- Tab bar -->
		<nav class="content-tabs__nav" aria-label="Content types">
			<button
				v-for="tab in tabs"
				:key="tab.id"
				:class="['content-tabs__tab', { 'content-tabs__tab--active': activeTab === tab.id }]"
				@click="selectTab(tab.id)"
			>
				<span :class="['content-tabs__icon', tab.icon]" aria-hidden="true" />
				<span class="content-tabs__label">{{ t('talk_content_browser', tab.label) }}</span>
			</button>
		</nav>

		<!-- Search bar (hidden on overview tab) -->
		<div v-if="activeTab !== 'overview'" class="content-tabs__search">
			<NcTextField
				:value="searchQuery"
				:label="t('talk_content_browser', 'Search…')"
				:show-trailing-button="searchQuery.length > 0"
				trailing-button-icon="close"
				@update:value="onSearch"
				@trailing-button-click="clearSearch"
			>
				<template #icon>
					<span class="icon-search" aria-hidden="true" />
				</template>
			</NcTextField>
		</div>

		<!-- Tab content slot -->
		<div class="content-tabs__body">
			<slot :active-tab="activeTab" :search-query="searchQuery" />
		</div>
	</div>
</template>

<script>
import { NcTextField } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import { TABS } from '../constants.js'

export default {
	name: 'ContentTabs',

	components: { NcTextField },

	props: {
		modelValue: {
			type: String,
			default: 'overview',
		},
	},

	emits: ['update:modelValue', 'search'],

	data() {
		return {
			tabs: TABS,
			searchQuery: '',
		}
	},

	computed: {
		activeTab() {
			return this.modelValue
		},
	},

	methods: {
		t,

		selectTab(tabId) {
			this.searchQuery = ''
			this.$emit('update:modelValue', tabId)
		},

		onSearch(val) {
			this.searchQuery = val
		},

		clearSearch() {
			this.searchQuery = ''
		},
	},
}
</script>

<style scoped>
.content-tabs {
	display: flex;
	flex-direction: column;
	height: 100%;
}

.content-tabs__nav {
	display: flex;
	flex-wrap: wrap;
	gap: 2px;
	padding: 8px 12px 0;
	border-bottom: 1px solid var(--color-border);
	background: var(--color-main-background);
	position: sticky;
	top: 0;
	z-index: 10;
}

.content-tabs__tab {
	display: inline-flex;
	align-items: center;
	gap: 5px;
	padding: 7px 12px;
	border: none;
	background: none;
	border-radius: 6px 6px 0 0;
	cursor: pointer;
	font-size: 14px;
	color: var(--color-text-maxcontrast);
	transition: background 0.15s, color 0.15s;
	white-space: nowrap;
}

.content-tabs__tab:hover {
	background: var(--color-background-hover);
	color: var(--color-main-text);
}

.content-tabs__tab--active {
	color: var(--color-primary-element);
	border-bottom: 2px solid var(--color-primary-element);
	font-weight: 600;
}

.content-tabs__icon {
	width: 16px;
	height: 16px;
	opacity: 0.7;
}

.content-tabs__tab--active .content-tabs__icon {
	opacity: 1;
}

.content-tabs__search {
	padding: 12px 16px 0;
}

.content-tabs__body {
	flex: 1;
	overflow-y: auto;
	padding: 12px 16px 24px;
}
</style>
