<template>
	<div class="media-gallery">
		<NcEmptyContent
			v-if="!loading && filtered.length === 0"
			:name="emptyTitle"
			:description="emptyDescription"
		>
			<template #icon>
				<span class="icon-picture" />
			</template>
		</NcEmptyContent>

		<div v-else class="media-gallery__grid">
			<div
				v-for="item in filtered"
				:key="item.id"
				:data-id="item.id"
				class="media-gallery__item"
				:title="item.messageParameters?.file?.name ?? ''"
				@click="openLightbox(item)"
			>
				<!-- Thumbnail — works for both images and videos via Nextcloud preview API -->
				<div class="media-gallery__thumb-wrap">
					<img
						:src="previewUrl(item)"
						:alt="item.messageParameters?.file?.name ?? ''"
						class="media-gallery__thumb"
						loading="lazy"
						@error="onThumbError"
					/>
					<span v-if="!isImage(item)" class="media-gallery__play-icon icon-play" aria-hidden="true" />
				</div>

				<div class="media-gallery__meta">
					<span class="media-gallery__name">{{ fileName(item) }}</span>
					<span class="media-gallery__date">{{ formatDate(item.timestamp) }}</span>
				</div>
			</div>
		</div>

		<div v-if="loading" class="media-gallery__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<div v-if="loadingMore" class="media-gallery__loading-more">
			<NcLoadingIcon :size="24" />
		</div>

		<div v-if="hasMore && !loading && !loadingMore" class="media-gallery__more">
			<NcButton @click="$emit('load-more')">
				{{ t('talk_browser', 'Load more') }}
			</NcButton>
		</div>

		<!-- Lightbox modal -->
		<NcModal
			v-if="lightboxItem"
			:name="fileName(lightboxItem)"
			size="large"
			@close="closeLightbox"
		>
			<div class="media-gallery__lightbox">
				<!-- Prev / Next nav -->
				<div class="media-gallery__lightbox-nav">
					<NcButton
						:disabled="lightboxIndex === 0"
						:aria-label="t('talk_browser', 'Previous')"
						type="tertiary"
						@click="stepLightbox(-1)"
					>
						<template #icon>
							<!-- chevron-left -->
							<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
								<polyline points="15 18 9 12 15 6" />
							</svg>
						</template>
					</NcButton>

					<span class="media-gallery__lightbox-counter">
						{{ lightboxIndex + 1 }} / {{ filtered.length }}
					</span>

					<NcButton
						:disabled="lightboxIndex === filtered.length - 1"
						:aria-label="t('talk_browser', 'Next')"
						type="tertiary"
						@click="stepLightbox(1)"
					>
						<template #icon>
							<!-- chevron-right -->
							<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
								<polyline points="9 18 15 12 9 6" />
							</svg>
						</template>
					</NcButton>
				</div>

				<img
					v-if="isImage(lightboxItem)"
					:key="lightboxItem.id"
					:src="fullUrl(lightboxItem)"
					:alt="fileName(lightboxItem)"
					class="media-gallery__lightbox-img"
					@error="onFullError"
				/>
				<video
					v-else
					:key="lightboxItem.id"
					:src="fullUrl(lightboxItem)"
					controls
					autoplay
					class="media-gallery__lightbox-video"
				/>
				<div class="media-gallery__lightbox-meta">
					<span class="media-gallery__lightbox-name">{{ fileName(lightboxItem) }}</span>
					<span class="media-gallery__lightbox-date">{{ formatDate(lightboxItem.timestamp) }}</span>
					<a
						:href="safeUrl(lightboxItem.messageParameters?.file?.link) || '#'"
						target="_blank"
						rel="noopener noreferrer"
						class="media-gallery__lightbox-open"
					>
						{{ t('talk_browser', 'Open in Files') }}
						<span class="icon-external" aria-hidden="true" />
					</a>
				</div>
			</div>
		</NcModal>
	</div>
</template>

<script>
import { NcButton, NcEmptyContent, NcLoadingIcon, NcModal } from '@nextcloud/vue'
import { getRootUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'
import { safeUrl, safeWebdavUrl, safeFileId } from '../utils/url.js'

export default {
	name: 'MediaGallery',

	components: { NcButton, NcEmptyContent, NcLoadingIcon, NcModal },

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

	beforeDestroy() {
		window.removeEventListener('keydown', this._onKeydown)
	},

	data() {
		return {
			lightboxIndex: null,
		}
	},

	computed: {
		lightboxItem() {
			return this.lightboxIndex !== null ? this.filtered[this.lightboxIndex] ?? null : null
		},

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
				: t('talk_browser', 'No images or videos yet')
		},

		emptyDescription() {
			return this.search
				? t('talk_browser', 'Try a different search term')
				: t('talk_browser', 'Share an image or video in this conversation to see it here')
		},
	},

	methods: {
		t,
		safeUrl,

		fileName(item) {
			return item.messageParameters?.file?.name
				?? item.messageParameters?.object?.name
				?? 'Unknown'
		},

		isImage(item) {
			const mime = item.messageParameters?.file?.mimetype ?? ''
			return mime.startsWith('image/')
		},

		previewUrl(item) {
			const fileId = safeFileId(item.messageParameters?.file?.id)
			if (!fileId) return ''
			return `${getRootUrl()}/index.php/core/preview?fileId=${fileId}&x=200&y=200&a=true`
		},

		fullUrl(item) {
			const file = item.messageParameters?.file
			if (!file) return ''
			// Images: use the high-res preview API
			if (this.isImage(item)) {
				const fileId = safeFileId(file.id)
				if (!fileId) return ''
				return `${getRootUrl()}/index.php/core/preview?fileId=${fileId}&x=2048&y=2048&a=true`
			}
			// Videos: stream directly via WebDAV
			return safeWebdavUrl(file.path)
		},

		formatDate(timestamp) {
			return new Date(timestamp * 1000).toLocaleDateString(undefined, {
				year: 'numeric', month: 'short', day: 'numeric',
			})
		},

		scrollToItem(id) {
			const safeId = parseInt(id, 10)
			if (!Number.isFinite(safeId)) return
			const el = this.$el.querySelector(`[data-id="${safeId}"]`)
			if (!el) return
			el.scrollIntoView({ behavior: 'smooth', block: 'center' })
			el.classList.add('media-gallery__item--highlight')
			setTimeout(() => el.classList.remove('media-gallery__item--highlight'), 2000)
		},

		openLightbox(item) {
			const idx = this.filtered.indexOf(item)
			this.lightboxIndex = idx >= 0 ? idx : null
			if (this.lightboxIndex !== null) {
				window.addEventListener('keydown', this._onKeydown)
			}
		},

		closeLightbox() {
			this.lightboxIndex = null
			window.removeEventListener('keydown', this._onKeydown)
		},

		stepLightbox(delta) {
			if (this.lightboxIndex === null) return
			const next = this.lightboxIndex + delta
			if (next >= 0 && next < this.filtered.length) {
				this.lightboxIndex = next
			}
		},

		_onKeydown(e) {
			if (e.key === 'ArrowLeft') this.stepLightbox(-1)
			else if (e.key === 'ArrowRight') this.stepLightbox(1)
		},

		onThumbError(e) {
			e.target.style.display = 'none'
			const wrap = e.target.closest('.media-gallery__thumb-wrap')
			if (wrap) wrap.classList.add('media-gallery__thumb-wrap--error')
		},

		onFullError(e) {
			e.target.style.display = 'none'
		},
	},
}
</script>

<style scoped>
.media-gallery__thumb-wrap {
	position: relative;
	width: 100%;
	height: 130px;
	background: var(--color-background-darker);
}

.media-gallery__thumb-wrap--error::after {
	content: '';
	display: block;
	width: 32px;
	height: 32px;
	position: absolute;
	top: 50%;
	left: 50%;
	transform: translate(-50%, -50%);
	opacity: 0.3;
	background-image: var(--icon-picture-dark);
	background-size: contain;
	background-repeat: no-repeat;
}

.media-gallery__play-icon {
	position: absolute;
	top: 50%;
	left: 50%;
	transform: translate(-50%, -50%);
	width: 36px;
	height: 36px;
	background: rgba(0, 0, 0, 0.55);
	border-radius: 50%;
	pointer-events: none;
}

.media-gallery__grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
	gap: 12px;
}

.media-gallery__item {
	border-radius: 8px;
	overflow: hidden;
	border: 1px solid var(--color-border);
	cursor: pointer;
	transition: box-shadow 0.15s, transform 0.15s;
	background: var(--color-background-dark);
}

.media-gallery__item:hover {
	box-shadow: 0 2px 12px rgba(0, 0, 0, 0.15);
	transform: translateY(-2px);
}

.media-gallery__thumb {
	width: 100%;
	height: 100%;
	object-fit: cover;
	display: block;
}

.media-gallery__meta {
	padding: 6px 8px;
}

.media-gallery__name {
	display: block;
	font-size: 12px;
	font-weight: 500;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.media-gallery__date {
	display: block;
	font-size: 11px;
	color: var(--color-text-maxcontrast);
}

.media-gallery__item--highlight {
	outline: 2px solid var(--color-primary-element);
	animation: tb-highlight-fade 2s ease forwards;
}

.media-gallery__loading,
.media-gallery__loading-more,
.media-gallery__more {
	display: flex;
	justify-content: center;
	padding: 20px 0;
}

/* Lightbox */
.media-gallery__lightbox {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 12px;
	padding: 16px;
	max-height: 80vh;
}

.media-gallery__lightbox-nav {
	display: flex;
	align-items: center;
	gap: 8px;
}

.media-gallery__lightbox-counter {
	min-width: 60px;
	text-align: center;
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

.media-gallery__lightbox-img,
.media-gallery__lightbox-video {
	max-width: 100%;
	max-height: calc(80vh - 80px);
	border-radius: 6px;
	object-fit: contain;
}

.media-gallery__lightbox-meta {
	display: flex;
	align-items: center;
	gap: 12px;
	font-size: 13px;
	color: var(--color-text-maxcontrast);
	flex-wrap: wrap;
	justify-content: center;
}

.media-gallery__lightbox-name {
	font-weight: 500;
	color: var(--color-main-text);
}

.media-gallery__lightbox-open {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	color: var(--color-primary-element);
}
</style>
