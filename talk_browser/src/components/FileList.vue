<template>
	<div class="file-list">
		<NcEmptyContent
			v-if="!loading && filtered.length === 0"
			:name="emptyTitle"
			:description="emptyDescription"
		>
			<template #icon>
				<span class="icon-files-dark" />
			</template>
		</NcEmptyContent>

		<ul v-else class="file-list__items">
			<li
				v-for="item in filtered"
				:key="item.id"
				:data-id="item.id"
				class="file-list__item"
			>
				<a
					:href="fileHref(item)"
					target="_blank"
					rel="noopener noreferrer"
					class="file-list__item-link"
					:aria-label="t('talk_browser', 'Open {name} in Files (opens in new tab)', { name: fileName(item) })"
				>
					<span class="file-list__icon-wrap">
						<img
							:src="mimeIconUrl(item)"
							class="file-list__icon"
							aria-hidden="true"
							alt=""
						>
					</span>

					<div class="file-list__info">
						<span class="file-list__name">{{ fileName(item) }}</span>
						<span class="file-list__meta">
							{{ formatSize(item.messageParameters?.file?.size) }}
							&middot;
							{{ formatDate(item.timestamp) }}
							&middot;
							<span class="file-list__sender">{{ item.actorDisplayName }}</span>
						</span>
					</div>

					<span class="icon-external file-list__open-icon" aria-hidden="true" />
				</a>
			</li>
		</ul>

		<div v-if="loading" class="file-list__loading" role="status" aria-live="polite">
			<NcLoadingIcon :size="32" aria-hidden="true" />
			<span class="sr-only">{{ t('talk_browser', 'Loading files…') }}</span>
		</div>

		<div v-if="loadingMore" class="file-list__loading-more" role="status" aria-live="polite">
			<NcLoadingIcon :size="24" aria-hidden="true" />
			<span class="sr-only">{{ t('talk_browser', 'Loading more files…') }}</span>
		</div>

		<div v-if="hasMore && !loading && !loadingMore" class="file-list__more">
			<NcButton @click="$emit('load-more')">
				{{ t('talk_browser', 'Load more') }}
			</NcButton>
		</div>
	</div>
</template>

<script>
import { NcButton, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import { safeUrl } from '../utils/url.js'

export default {
	name: 'FileList',

	components: { NcButton, NcEmptyContent, NcLoadingIcon },

	props: {
		items: { type: Array, default: () => [] },
		loading: { type: Boolean, default: false },
		loadingMore: { type: Boolean, default: false },
		hasMore: { type: Boolean, default: false },
		search: { type: String, default: '' },
		highlightId: { type: Number, default: null },
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
				this.fileName(item).toLowerCase().includes(q),
			)
		},

		emptyTitle() {
			return this.search
				? t('talk_browser', 'No results for "{search}"', { search: this.search })
				: t('talk_browser', 'No files yet')
		},

		emptyDescription() {
			return this.search
				? t('talk_browser', 'Try a different search term')
				: t('talk_browser', 'Share a file in this conversation to see it here')
		},
	},

	methods: {
		t,

		scrollToItem(id) {
			const safeId = parseInt(id, 10)
			if (!Number.isFinite(safeId)) return
			const el = this.$el.querySelector(`[data-id="${safeId}"]`)
			if (!el) return
			el.scrollIntoView({ behavior: 'smooth', block: 'center' })
			el.classList.add('file-list__item--highlight')
			setTimeout(() => el.classList.remove('file-list__item--highlight'), 2000)
		},

		fileName(item) {
			return item.messageParameters?.file?.name
				?? item.messageParameters?.object?.name
				?? 'Unknown file'
		},

		mimeIconUrl(item) {
			const mime = item.messageParameters?.file?.mimetype ?? 'application/octet-stream'
			// OC.MimeType.getIconUrl is provided by Nextcloud core globally
			// and returns a themed SVG URL for any MIME type.
			return window.OC?.MimeType?.getIconUrl(mime)
				?? `/core/img/filetypes/file.svg`
		},

		formatSize(bytes) {
			if (!bytes) return ''
			const kb = bytes / 1024
			if (kb < 1024) return `${kb.toFixed(1)} KB`
			const mb = kb / 1024
			if (mb < 1024) return `${mb.toFixed(1)} MB`
			return `${(mb / 1024).toFixed(1)} GB`
		},

		formatDate(timestamp) {
			return new Date(timestamp * 1000).toLocaleDateString(undefined, {
				year: 'numeric', month: 'short', day: 'numeric',
			})
		},

		openItem(item) {
			const link = safeUrl(item.messageParameters?.file?.link
				?? item.messageParameters?.object?.link)
			if (link) {
				window.open(link, '_blank', 'noopener,noreferrer')
			}
		},

		fileHref(item) {
			return safeUrl(item.messageParameters?.file?.link
				?? item.messageParameters?.object?.link) || '#'
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

.file-list__items {
	list-style: none;
	margin: 0;
	padding: 0;
}

.file-list__item {
	border-radius: 8px;
	overflow: hidden;
}

.file-list__item-link {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 10px 12px;
	border-radius: 8px;
	cursor: pointer;
	transition: background 0.1s;
	text-decoration: none;
	color: inherit;
}

.file-list__item-link:hover {
	background: var(--color-background-hover);
}

.file-list__item--highlight {
	outline: 2px solid var(--color-primary-element);
	animation: tb-highlight-fade 2s ease forwards;
}

.file-list__icon-wrap {
	flex-shrink: 0;
	width: 36px;
	height: 36px;
	border-radius: 6px;
	background: var(--color-background-dark);
	display: flex;
	align-items: center;
	justify-content: center;
}

.file-list__icon {
	width: 22px;
	height: 22px;
	object-fit: contain;
}

.file-list__info {
	flex: 1;
	min-width: 0;
}

.file-list__name {
	display: block;
	font-weight: 500;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.file-list__meta {
	display: block;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.file-list__open-icon {
	flex-shrink: 0;
	width: 16px;
	height: 16px;
	opacity: 0.4;
}

.file-list__loading,
.file-list__loading-more,
.file-list__more {
	display: flex;
	justify-content: center;
	padding: 20px 0;
}
</style>
