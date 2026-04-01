<template>
	<div class="overview">
		<div v-if="loading" class="overview__loading" role="status" aria-live="polite">
			<NcLoadingIcon :size="40" aria-hidden="true" />
			<span class="sr-only">{{ t('talk_browser', 'Loading overview…') }}</span>
		</div>

		<NcEmptyContent
			v-else-if="error"
			:name="t('talk_browser', 'Could not load overview')"
			:description="t('talk_browser', 'There was a problem loading the overview. Please try selecting the conversation again.')"
		>
			<template #icon>
				<span class="icon-error" aria-hidden="true" />
			</template>
		</NcEmptyContent>

		<NcEmptyContent
			v-else-if="isEmpty"
			:name="t('talk_browser', 'No shared content yet')"
			:description="t('talk_browser', 'Share files, images, audio, or locations in this conversation to see them here. Plain links appear in the Links tab.')"
		>
			<template #icon>
				<span class="icon-home" aria-hidden="true" />
			</template>
		</NcEmptyContent>

		<div v-else class="overview__sections">
			<section
				v-for="section in sections"
				:key="section.id"
				class="overview__section"
			>
				<div class="overview__section-header">
					<h2 class="overview__section-title">
						<span :class="['overview__section-icon', section.icon]" aria-hidden="true" />
						{{ t('talk_browser', section.label) }}
					</h2>
					<button
						class="overview__see-all"
						:aria-label="t('talk_browser', 'See all {section}', { section: t('talk_browser', section.label) })"
						@click="$emit('go-to-tab', section.id)"
					>
						{{ t('talk_browser', 'See all') }}
					</button>
				</div>

					<!-- Media: grid thumbnails -->
				<div v-if="section.id === 'media'" class="overview__media-row">
					<button
						v-for="item in section.items"
						:key="item.id"
						class="overview__media-thumb"
						:aria-label="t('talk_browser', 'Open {name} in media tab', { name: item.messageParameters && item.messageParameters.file ? item.messageParameters.file.name : 'item' })"
						@click="$emit('go-to-item', { tab: section.id, id: item.id })"
					>
						<img
							:src="previewUrl(item)"
							:alt="''"
							loading="lazy"
							aria-hidden="true"
						/>
					</button>
				</div>

				<!-- Files / Audio / Other: compact list -->
				<ul v-else class="overview__list">
					<li
						v-for="item in section.items"
						:key="item.id"
						class="overview__list-item"
					>
						<button
							class="overview__list-item-btn"
							:aria-label="t('talk_browser', 'Open {name}', { name: itemName(item) })"
							@click="$emit('go-to-item', { tab: section.id, id: item.id })"
						>
							<span :class="['overview__item-icon', section.icon]" aria-hidden="true" />
							<span class="overview__item-name">{{ itemName(item) }}</span>
							<span class="overview__item-date">{{ formatDate(item.timestamp) }}</span>
						</button>
					</li>
				</ul>
			</section>
		</div>
	</div>
</template>

<script>
import { NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import { getRootUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'
import { TABS } from '../constants.js'
import { safeFileId } from '../utils/url.js'

export default {
	name: 'OverviewPanel',

	components: { NcEmptyContent, NcLoadingIcon },

	props: {
		overviewData: {
			type: Object,
			default: () => ({}),
		},
		loading: {
			type: Boolean,
			default: false,
		},
		error: {
			type: String,
			default: null,
		},
	},

	computed: {
		sections() {
			return TABS
				.filter(tab => tab.objectType && tab.objectType !== 'links' && tab.objectType !== 'overview')
				.map(tab => ({
					...tab,
					items: this.overviewData[tab.objectType] ?? [],
				}))
				.filter(section => section.items.length > 0)
		},

		isEmpty() {
			return this.sections.length === 0
		},
	},

	methods: {
		t,

		itemName(item) {
			return item.messageParameters?.file?.name
				?? item.messageParameters?.object?.name
				?? 'Item'
		},

		previewUrl(item) {
			const fileId = safeFileId(item.messageParameters?.file?.id)
			if (!fileId) return ''
			return `${getRootUrl()}/index.php/core/preview?fileId=${fileId}&x=120&y=120&a=true`
		},

		formatDate(timestamp) {
			return new Date(timestamp * 1000).toLocaleDateString(undefined, {
				year: 'numeric', month: 'short', day: 'numeric',
			})
		},
	},
}
</script>

<style scoped>
.sr-only {
	position: absolute;
	width: 1px;
	height: 1px;
	padding: 0;
	margin: -1px;
	overflow: hidden;
	clip: rect(0, 0, 0, 0);
	white-space: nowrap;
	border: 0;
}

.overview__loading {
	display: flex;
	justify-content: center;
	margin: 48px auto;
}

.overview__sections {
	display: flex;
	flex-direction: column;
	gap: 28px;
}

.overview__section-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 8px;
}

.overview__section-title {
	display: flex;
	align-items: center;
	gap: 6px;
	font-size: 15px;
	font-weight: 600;
	margin: 0;
}

.overview__section-icon {
	width: 16px;
	height: 16px;
	opacity: 0.75;
}

.overview__see-all {
	background: none;
	border: none;
	color: var(--color-primary-element);
	cursor: pointer;
	font-size: 13px;
	padding: 2px 6px;
	border-radius: 4px;
}

.overview__see-all:hover {
	background: var(--color-background-hover);
}

/* Media grid row */
.overview__media-row {
	display: flex;
	gap: 6px;
	flex-wrap: wrap;
}

.overview__media-thumb {
	width: 80px;
	height: 80px;
	border-radius: 6px;
	overflow: hidden;
	cursor: pointer;
	flex-shrink: 0;
	background: var(--color-background-dark);
	border: none;
	padding: 0;
}

.overview__media-thumb img {
	width: 100%;
	height: 100%;
	object-fit: cover;
	transition: transform 0.15s;
}

.overview__media-thumb:hover img {
	transform: scale(1.06);
}

/* Compact list */
.overview__list {
	list-style: none;
	margin: 0;
	padding: 0;
	display: flex;
	flex-direction: column;
	gap: 2px;
}

.overview__list-item {
	display: contents;
}

.overview__list-item-btn {
	display: flex;
	align-items: center;
	gap: 8px;
	width: 100%;
	padding: 6px 8px;
	border-radius: 6px;
	cursor: pointer;
	transition: background 0.1s;
	background: none;
	border: none;
	text-align: left;
}

.overview__list-item-btn:hover {
	background: var(--color-background-hover);
}

.overview__item-icon {
	width: 16px;
	height: 16px;
	flex-shrink: 0;
	opacity: 0.6;
}

.overview__item-name {
	flex: 1;
	min-width: 0;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	font-size: 13px;
}

.overview__item-date {
	flex-shrink: 0;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}
</style>
