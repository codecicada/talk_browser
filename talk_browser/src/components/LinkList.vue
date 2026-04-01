<template>
	<div class="link-list">
		<NcEmptyContent
			v-if="!loading && filtered.length === 0"
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
			:data-id="item.id"
			class="link-list__item"
		>
			<a
				:href="safeUrl(item.url) || '#'"
				target="_blank"
				rel="noopener noreferrer"
				class="link-list__link"
			>
				<!-- OG image thumbnail (proxied); falls back to link SVG icon -->
				<img
					v-if="!ogFailed[item.id]"
					:src="ogProxyUrl(item.url)"
					alt=""
					class="link-list__og-thumb"
					loading="lazy"
					@error="onOgError(item.id)"
				/>
				<svg v-else class="link-list__link-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
					<path fill="currentColor" d="M10.59 13.41c.41.39.41 1.03 0 1.42c-.39.41-1.03.41-1.42 0a5.003 5.003 0 0 1 0-7.07l3.54-3.54a5.003 5.003 0 0 1 7.07 0a5.003 5.003 0 0 1 0 7.07l-1.49 1.49c.01-.82-.12-1.64-.4-2.42l.47-.48a2.982 2.982 0 0 0 0-4.24a2.982 2.982 0 0 0-4.24 0l-3.53 3.53a2.982 2.982 0 0 0 0 4.24m2.82-4.24c.39-.41 1.03-.41 1.42 0a5.003 5.003 0 0 1 0 7.07l-3.54 3.54a5.003 5.003 0 0 1-7.07 0a5.003 5.003 0 0 1 0-7.07l1.49-1.49c-.01.82.12 1.64.4 2.43l-.47.47a2.982 2.982 0 0 0 0 4.24a2.982 2.982 0 0 0 4.24 0l3.53-3.53a2.982 2.982 0 0 0 0-4.24a.973.973 0 0 1 0-1.42Z"/>
				</svg>

				<div class="link-list__info">
					<span class="link-list__title">
						{{ displayTitle(item) }}
						<span v-if="item.count > 1" class="link-list__count" :title="t('talk_browser', '{count} times shared', { count: item.count })">
							&times;{{ item.count }}
						</span>
					</span>
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

		<div v-if="loading" class="link-list__loading" role="status" aria-live="polite">
			<NcLoadingIcon :size="32" aria-hidden="true" />
			<span v-if="!linkScanDone" class="link-list__scan-note">
				{{ t('talk_browser', 'Scanning message history for links…') }}
			</span>
			<span v-else class="sr-only">{{ t('talk_browser', 'Loading…') }}</span>
		</div>

		<div v-if="loadingMore" class="link-list__loading-more" role="status" aria-live="polite">
			<NcLoadingIcon :size="24" aria-hidden="true" />
			<span class="link-list__scan-note">
				{{ t('talk_browser', 'Scanning more history…') }}
			</span>
		</div>

		<div v-if="hasMore && !loading && !loadingMore" class="link-list__more">
			<NcButton @click="$emit('load-more')">
				{{ t('talk_browser', 'Scan more history') }}
			</NcButton>
		</div>
	</div>
</template>

<script>
import { NcButton, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { safeUrl } from '../utils/url.js'

export default {
	name: 'LinkList',

	components: { NcButton, NcEmptyContent, NcLoadingIcon },

	props: {
		items: { type: Array, default: () => [] },
		loading: { type: Boolean, default: false },
		loadingMore: { type: Boolean, default: false },
		hasMore: { type: Boolean, default: false },
		linkScanDone: { type: Boolean, default: false },
		search: { type: String, default: '' },
		highlightId: { type: Number, default: null },
	},

	data() {
		return {
			// Map of item.id → true when the OG image failed/is absent
			ogFailed: {},
		}
	},

	watch: {
		highlightId(id) {
			if (!id) return
			this.$nextTick(() => this.scrollToItem(id))
		},
	},

	mounted() {
		if (this.highlightId) {
			this.$nextTick(() => this.scrollToItem(this.highlightId))
		}
	},

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
				? t('talk_browser', 'No results for "{search}"', { search: this.search })
				: t('talk_browser', 'No links found yet')
		},

		emptyDescription() {
			return this.search
				? t('talk_browser', 'Try a different search term')
				: t('talk_browser', 'Type a URL into this conversation to see it here')
		},
	},

	methods: {
		t,
		safeUrl,

		ogProxyUrl(url) {
			return generateUrl('/apps/talk_browser/og-image') + '?url=' + encodeURIComponent(url)
		},

		onOgError(id) {
			this.$set(this.ogFailed, id, true)
		},

		scrollToItem(id) {
			const safeId = parseInt(id, 10)
			if (!Number.isFinite(safeId)) return
			const el = this.$el.querySelector(`[data-id="${safeId}"]`)
			if (!el) return
			el.scrollIntoView({ behavior: 'smooth', block: 'center' })
			el.classList.add('link-list__item--highlight')
			setTimeout(() => el.classList.remove('link-list__item--highlight'), 2000)
		},

		displayTitle(item) {
			// Use message text as title if it's more than just the bare URL
			return item.title && item.title !== item.url ? item.title : item.url
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

.link-list__items {
	list-style: none;
	margin: 0;
	padding: 0;
}

.link-list__item {
	border-radius: 8px;
	overflow: hidden;
}

.link-list__item--highlight {
	outline: 2px solid var(--color-primary-element);
	animation: tb-highlight-fade 2s ease forwards;
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

/* OG image thumbnail */
.link-list__og-thumb {
	width: 64px;
	height: 64px;
	flex-shrink: 0;
	object-fit: cover;
	border-radius: 6px;
	background: var(--color-background-dark);
}

/* Fallback link SVG icon */
.link-list__link-icon {
	width: 20px;
	height: 20px;
	flex-shrink: 0;
	margin-top: 2px;
	color: var(--color-text-maxcontrast);
	opacity: 0.7;
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

.link-list__count {
	display: inline-block;
	font-size: 11px;
	font-weight: 600;
	padding: 0 5px;
	margin-left: 5px;
	border-radius: 10px;
	background: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
	vertical-align: middle;
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

.link-list__loading,
.link-list__loading-more,
.link-list__more {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 8px;
	padding: 20px 0;
}

.link-list__more {
	flex-direction: row;
	justify-content: center;
}

.link-list__scan-note {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}
</style>

