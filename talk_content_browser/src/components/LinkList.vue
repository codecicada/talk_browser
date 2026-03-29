<template>
	<div class="link-list">
		<NcEmptyContent
			v-if="!loading && filtered.length === 0 && (linkScanDone || !hasMore)"
			:name="emptyTitle"
			:description="emptyDescription"
		>
			<template #icon>
				<span class="icon-link" />
			</template>
		</NcEmptyContent>

		<ul v-else class="link-list__items">
			<li
				v-for="item in filtered"
				:key="item.id"
				class="link-list__item"
			>
				<a
					:href="item.url"
					target="_blank"
					rel="noopener noreferrer"
					class="link-list__link"
				>
					<!-- Favicon -->
					<img
						:src="faviconUrl(item.url)"
						:alt="''"
						class="link-list__favicon"
						loading="lazy"
						@error="onFaviconError"
					/>

					<div class="link-list__info">
						<span class="link-list__title">{{ displayTitle(item) }}</span>
						<span class="link-list__url">{{ item.url }}</span>
						<span class="link-list__meta">
							{{ item.actorDisplayName }}
							&middot;
							{{ formatDate(item.timestamp) }}
						</span>
					</div>

					<span class="icon-external link-list__open-icon" aria-hidden="true" />
				</a>
			</li>
		</ul>

		<div v-if="loading" class="link-list__loading">
			<NcLoadingIcon :size="32" />
			<span v-if="!linkScanDone" class="link-list__scan-note">
				{{ t('talk_content_browser', 'Scanning message history for links…') }}
			</span>
		</div>

		<div v-if="hasMore && !loading" class="link-list__more">
			<NcButton @click="$emit('load-more')">
				{{ t('talk_content_browser', 'Scan more history') }}
			</NcButton>
		</div>
	</div>
</template>

<script>
import { NcButton, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'LinkList',

	components: { NcButton, NcEmptyContent, NcLoadingIcon },

	props: {
		items: { type: Array, default: () => [] },
		loading: { type: Boolean, default: false },
		hasMore: { type: Boolean, default: false },
		linkScanDone: { type: Boolean, default: false },
		search: { type: String, default: '' },
	},

	emits: ['load-more'],

	computed: {
		filtered() {
			if (!this.search) return this.items
			const q = this.search.toLowerCase()
			return this.items.filter(item =>
				item.url.toLowerCase().includes(q)
				|| this.displayTitle(item).toLowerCase().includes(q),
			)
		},

		emptyTitle() {
			return this.search
				? t('talk_content_browser', 'No results for "{search}"', { search: this.search })
				: t('talk_content_browser', 'No links found yet')
		},

		emptyDescription() {
			return this.search
				? t('talk_content_browser', 'Try a different search term')
				: t('talk_content_browser', 'Type a URL into this conversation to see it here')
		},
	},

	methods: {
		t,

		displayTitle(item) {
			// Use message text as title if it's more than just the bare URL
			return item.title && item.title !== item.url ? item.title : item.url
		},

		faviconUrl(url) {
			try {
				const origin = new URL(url).origin
				return `${origin}/favicon.ico`
			} catch {
				return ''
			}
		},

		onFaviconError(e) {
			// Hide broken favicon images gracefully
			e.target.style.display = 'none'
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
.link-list__items {
	list-style: none;
	margin: 0;
	padding: 0;
}

.link-list__item {
	border-radius: 8px;
	overflow: hidden;
}

.link-list__link {
	display: flex;
	align-items: flex-start;
	gap: 12px;
	padding: 10px 12px;
	border-radius: 8px;
	text-decoration: none;
	color: inherit;
	transition: background 0.1s;
}

.link-list__link:hover {
	background: var(--color-background-hover);
}

.link-list__favicon {
	width: 18px;
	height: 18px;
	flex-shrink: 0;
	margin-top: 2px;
	border-radius: 3px;
}

.link-list__info {
	flex: 1;
	min-width: 0;
}

.link-list__title {
	display: block;
	font-weight: 500;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	color: var(--color-primary-element);
}

.link-list__url {
	display: block;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.link-list__meta {
	display: block;
	font-size: 11px;
	color: var(--color-text-maxcontrast);
	margin-top: 2px;
}

.link-list__open-icon {
	flex-shrink: 0;
	width: 16px;
	height: 16px;
	opacity: 0.4;
	margin-top: 4px;
}

.link-list__loading {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 8px;
	padding: 20px 0;
}

.link-list__scan-note {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

.link-list__more {
	display: flex;
	justify-content: center;
	padding: 20px 0;
}
</style>
