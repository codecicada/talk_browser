<template>
	<div class="generic-list">
		<NcEmptyContent
			v-if="!loading && filtered.length === 0"
			:name="emptyTitle"
			:description="emptyDescription"
		>
			<template #icon>
				<span :class="emptyIcon" />
			</template>
		</NcEmptyContent>

		<ul v-else class="generic-list__items">
			<li
				v-for="item in filtered"
				:key="item.id"
				class="generic-list__item"
				@click="openItem(item)"
			>
				<span :class="['generic-list__icon', itemIcon(item)]" aria-hidden="true" />

				<div class="generic-list__info">
					<span class="generic-list__name">{{ itemName(item) }}</span>
					<span class="generic-list__meta">
						{{ item.actorDisplayName }}
						&middot;
						{{ formatDate(item.timestamp) }}
					</span>
					<span v-if="itemDescription(item)" class="generic-list__desc">
						{{ itemDescription(item) }}
					</span>
					<a
						v-if="mapUrl(item)"
						:href="mapUrl(item)"
						target="_blank"
						rel="noopener noreferrer"
						class="generic-list__map-link"
						@click.stop
					>
						{{ t('talk_browser', 'Open on map') }}
						<span class="icon-external" aria-hidden="true" />
					</a>
				</div>

				<span class="icon-external generic-list__open-icon" aria-hidden="true" />
			</li>
		</ul>

		<div v-if="loading" class="generic-list__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<div v-if="loadingMore" class="generic-list__loading-more">
			<NcLoadingIcon :size="24" />
		</div>

		<div v-if="hasMore && !loading && !loadingMore" class="generic-list__more">
			<NcButton @click="$emit('load-more')">
				{{ t('talk_browser', 'Load more') }}
			</NcButton>
		</div>
	</div>
</template>

<script>
import { NcButton, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'GenericList',

	components: { NcButton, NcEmptyContent, NcLoadingIcon },

	props: {
		items: { type: Array, default: () => [] },
		loading: { type: Boolean, default: false },
		loadingMore: { type: Boolean, default: false },
		hasMore: { type: Boolean, default: false },
		search: { type: String, default: '' },
		objectType: { type: String, default: 'other' },
	},

	computed: {
		filtered() {
			if (!this.search) return this.items
			const q = this.search.toLowerCase()
			return this.items.filter(item =>
				this.itemName(item).toLowerCase().includes(q)
				|| (item.actorDisplayName ?? '').toLowerCase().includes(q),
			)
		},

		emptyIcon() {
			if (this.objectType === 'location') return 'icon-address'
			return 'icon-more'
		},

		emptyTitle() {
			if (this.search) {
				return t('talk_browser', 'No results for "{search}"', { search: this.search })
			}
			if (this.objectType === 'location') return t('talk_browser', 'No locations yet')
			if (this.objectType === 'deckcard') return t('talk_browser', 'No Deck cards yet')
			if (this.objectType === 'recording') return t('talk_browser', 'No recordings yet')
			return t('talk_browser', 'Nothing here yet')
		},

		emptyDescription() {
			return this.search
				? t('talk_browser', 'Try a different search term')
				: t('talk_browser', 'Share content in this conversation to see it here')
		},
	},

	methods: {
		t,

		itemName(item) {
			return item.messageParameters?.object?.name
				?? item.messageParameters?.file?.name
				?? 'Item'
		},

		itemDescription(item) {
			const obj = item.messageParameters?.object
			if (!obj) return null
			if (obj.type === 'geo-location') {
				return `${obj.latitude}, ${obj.longitude}`
			}
			return obj.description ?? null
		},

		itemIcon(item) {
			const obj = item.messageParameters?.object
			if (!obj) return 'icon-more'
			switch (obj.type) {
			case 'geo-location': return 'icon-address'
			case 'deck-card': return 'icon-deck'
			default: return 'icon-more'
			}
		},

		formatDate(timestamp) {
			return new Date(timestamp * 1000).toLocaleDateString(undefined, {
				year: 'numeric', month: 'short', day: 'numeric',
			})
		},

		mapUrl(item) {
			const obj = item.messageParameters?.object
			if (obj?.type !== 'geo-location') return null
			return `https://www.openstreetmap.org/?mlat=${obj.latitude}&mlon=${obj.longitude}&zoom=15`
		},

		openItem(item) {
			const link = item.messageParameters?.object?.link
				?? item.messageParameters?.file?.link
			if (link) {
				window.open(link, '_blank', 'noopener')
			}
		},
	},
}
</script>

<style scoped>
.generic-list__items {
	list-style: none;
	margin: 0;
	padding: 0;
}

.generic-list__item {
	display: flex;
	align-items: flex-start;
	gap: 12px;
	padding: 10px 12px;
	border-radius: 8px;
	cursor: pointer;
	transition: background 0.1s;
}

.generic-list__item:hover {
	background: var(--color-background-hover);
}

.generic-list__icon {
	flex-shrink: 0;
	width: 20px;
	height: 20px;
	margin-top: 2px;
	opacity: 0.7;
}

.generic-list__info {
	flex: 1;
	min-width: 0;
}

.generic-list__name {
	display: block;
	font-weight: 500;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.generic-list__meta {
	display: block;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.generic-list__desc {
	display: block;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	margin-top: 2px;
}

.generic-list__open-icon {
	flex-shrink: 0;
	width: 16px;
	height: 16px;
	opacity: 0.4;
	margin-top: 4px;
}

.generic-list__map-link {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	font-size: 12px;
	color: var(--color-primary-element);
	margin-top: 2px;
}

.generic-list__loading,
.generic-list__loading-more,
.generic-list__more {
	display: flex;
	justify-content: center;
	padding: 20px 0;
}
</style>
