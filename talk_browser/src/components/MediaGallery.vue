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
				class="media-gallery__item"
				:title="item.messageParameters?.file?.name ?? ''"
				@click="openItem(item)"
			>
		<!-- Thumbnail — works for both images and videos via Nextcloud preview API -->
			<div class="media-gallery__thumb-wrap">
				<img
					:src="previewUrl(item)"
					:alt="item.messageParameters?.file?.name ?? ''"
					class="media-gallery__thumb"
					loading="lazy"
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

		<div v-if="hasMore && !loading" class="media-gallery__more">
			<NcButton @click="$emit('load-more')">
				{{ t('talk_browser', 'Load more') }}
			</NcButton>
		</div>
	</div>
</template>

<script>
import { NcButton, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import { getRootUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'MediaGallery',

	components: { NcButton, NcEmptyContent, NcLoadingIcon },

	props: {
		items: { type: Array, default: () => [] },
		loading: { type: Boolean, default: false },
		hasMore: { type: Boolean, default: false },
		search: { type: String, default: '' },
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
			const fileId = item.messageParameters?.file?.id
			if (!fileId) return ''
			// Use getRootUrl() to avoid the /index.php/ prefix added by generateUrl()
			return `${getRootUrl()}/index.php/core/preview?fileId=${fileId}&x=200&y=200&a=true`
		},

		formatDate(timestamp) {
			return new Date(timestamp * 1000).toLocaleDateString(undefined, {
				year: 'numeric', month: 'short', day: 'numeric',
			})
		},

		openItem(item) {
			const link = item.messageParameters?.file?.link
				?? item.messageParameters?.object?.link
			if (link) {
				window.open(link, '_blank', 'noopener')
			}
		},
	},
}
</script>

<style scoped>
.media-gallery__thumb-wrap {
	position: relative;
	width: 100%;
	height: 130px;
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

.media-gallery__video-thumb {
	width: 100%;
	height: 130px;
	display: flex;
	align-items: center;
	justify-content: center;
	background: var(--color-background-darker);
	font-size: 40px;
	opacity: 0.5;
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

.media-gallery__loading,
.media-gallery__more {
	display: flex;
	justify-content: center;
	padding: 20px 0;
}
</style>
