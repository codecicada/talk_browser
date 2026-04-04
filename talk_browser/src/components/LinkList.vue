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
			<!-- Outer div is non-interactive; only the title anchor and open-icon anchor navigate -->
			<div class="link-list__link">
				<!-- OG image thumbnail (proxied); falls back to link SVG icon — non-interactive -->
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
					<!-- Title anchor: opens external URL -->
					<a
						:href="safeUrl(item.url) || '#'"
						target="_blank"
						rel="noopener noreferrer"
						class="link-list__title"
					>
						{{ resolvedTitle(item) }}
						<span v-if="item.count > 1" class="link-list__count" :title="t('talk_browser', '{count} times shared', { count: item.count })">
							&times;{{ item.count }}
						</span>
					</a>
					<span
						v-if="resolvedDescription(item)"
						class="link-list__description"
					>{{ resolvedDescription(item) }}</span>
					<span class="link-list__url">{{ item.url }}</span>
					<span class="link-list__meta">
						{{ item.actorDisplayName }}
						&middot;
						{{ formatDate(item.timestamp) }}
						&middot;
						<!-- Go to message: navigates to the Talk conversation at the exact message -->
						<a
							:href="generateUrl('/call/' + item.conversationToken) + '#message_' + item.messageId"
							target="_blank"
							rel="noopener noreferrer"
							class="link-list__goto"
						>{{ t('talk_browser', 'Go to message') }}</a>
					</span>
				</div>

				<!-- External icon anchor: also opens external URL -->
				<a
					:href="safeUrl(item.url) || '#'"
					target="_blank"
					rel="noopener noreferrer"
					class="link-list__open-icon-link"
					:aria-label="t('talk_browser', 'Open link in new tab')"
				>
					<span class="icon-external link-list__open-icon" aria-hidden="true" />
				</a>
			</div>
		</li>
		</ul>

		<div v-if="loading" class="link-list__loading" role="status" aria-live="polite">
			<NcLoadingIcon :size="32" aria-hidden="true" />
			<span class="link-list__scan-note">
				{{ t('talk_browser', 'Scanning message history for links…') }}
			</span>
		</div>
	</div>
</template>

<script>
import { NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { safeUrl } from '../utils/url.js'
import { fetchOgMeta } from '../api/talk.js'
import { sortItems } from '../utils/sort.js'

export default {
	name: 'LinkList',

	components: { NcEmptyContent, NcLoadingIcon },

	props: {
		items: { type: Array, default: () => [] },
		loading: { type: Boolean, default: false },
		search: { type: String, default: '' },
		sort: { type: String, default: 'date-desc' },
		highlightId: { type: Number, default: null },
	},

	data() {
		return {
			// Map of item.id → true when the OG image failed/is absent
			ogFailed: {},
			// Map of item.url → { title: string|null, description: string|null }
			ogMeta: {},
			// Set of URLs currently being fetched (avoid duplicate in-flight requests)
			ogMetaFetching: new Set(),
		}
	},

	watch: {
		highlightId(id) {
			if (!id) return
			this.$nextTick(() => this.scrollToItem(id))
		},

		filtered(newItems) {
			this.prefetchOgMeta(newItems)
		},
	},

	mounted() {
		if (this.highlightId) {
			this.$nextTick(() => this.scrollToItem(this.highlightId))
		}
		this.prefetchOgMeta(this.filtered)
	},

	computed: {
		filtered() {
			let result = this.items
			if (this.search) {
				const q = this.search.toLowerCase()
				result = result.filter(item =>
					item.url.toLowerCase().includes(q)
					|| this.resolvedTitle(item).toLowerCase().includes(q)
					|| (this.resolvedDescription(item) || '').toLowerCase().includes(q),
				)
			}
			return sortItems(result, this.sort, item => this.resolvedTitle(item))
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
		generateUrl,

		ogProxyUrl(url) {
			return generateUrl('/apps/talk_browser/api/og-image') + '?url=' + encodeURIComponent(url)
		},

		onOgError(id) {
			this.$set(this.ogFailed, id, true)
		},

		/**
		 * Kick off OG meta fetches for all visible items that haven't been
		 * fetched yet. Requests are deduplicated by URL.
		 */
		prefetchOgMeta(items) {
			for (const item of items) {
				if (this.ogMeta[item.url] !== undefined) continue
				if (this.ogMetaFetching.has(item.url)) continue
				this.ogMetaFetching.add(item.url)
				fetchOgMeta(item.url)
					.then(meta => {
						this.$set(this.ogMeta, item.url, meta)
					})
					.catch(() => {
						this.$set(this.ogMeta, item.url, { title: null, description: null })
					})
					.finally(() => {
						this.ogMetaFetching.delete(item.url)
					})
			}
		},

		/**
		 * Best available title: OG title > message-text title > bare URL.
		 */
		resolvedTitle(item) {
			const og = this.ogMeta[item.url]
			if (og?.title) return og.title
			return item.title && item.title !== item.url ? item.title : item.url
		},

		/**
		 * OG description if available (null otherwise — caller must guard).
		 */
		resolvedDescription(item) {
			return this.ogMeta[item.url]?.description ?? null
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

/* Outer wrapper is a plain div — no pointer cursor, subtle hover for the whole row */
.link-list__link {
	display: flex;
	align-items: flex-start;
	gap: 12px;
	padding: 10px 12px;
	border-radius: 8px;
	cursor: default;
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

/* Title is now an anchor — keep it looking the same, add pointer + underline on hover */
.link-list__title {
	display: block;
	font-weight: 500;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	color: var(--color-primary-element);
	text-decoration: none;
	cursor: pointer;
}

.link-list__title:hover,
.link-list__title:focus {
	text-decoration: underline;
	outline: none;
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

.link-list__description {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	margin-top: 2px;
	/* Allow up to 2 lines before truncating */
	display: -webkit-box;
	-webkit-line-clamp: 2;
	-webkit-box-orient: vertical;
	overflow: hidden;
}

.link-list__url {
	display: block;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	margin-top: 2px;
}

.link-list__meta {
	display: block;
	font-size: 11px;
	color: var(--color-text-maxcontrast);
	margin-top: 2px;
}

/* "Go to message" link — secondary style, muted, underlines on hover */
.link-list__goto {
	color: var(--color-text-maxcontrast);
	text-decoration: none;
	cursor: pointer;
}

.link-list__goto:hover,
.link-list__goto:focus {
	color: var(--color-main-text);
	text-decoration: underline;
	outline: none;
}

/* External icon anchor wrapper */
.link-list__open-icon-link {
	display: flex;
	align-items: center;
	flex-shrink: 0;
	cursor: pointer;
	color: inherit;
	text-decoration: none;
	margin-top: 4px;
}

.link-list__open-icon-link:hover .link-list__open-icon,
.link-list__open-icon-link:focus .link-list__open-icon {
	opacity: 0.8;
}

.link-list__open-icon {
	width: 16px;
	height: 16px;
	opacity: 0.4;
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
</style>
