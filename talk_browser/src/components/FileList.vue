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
				class="file-list__item"
				@click="openItem(item)"
			>
				<span class="file-list__icon-wrap">
					<span :class="['file-list__icon', mimeIcon(item)]" aria-hidden="true" />
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
			</li>
		</ul>

		<div v-if="loading" class="file-list__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<div v-if="hasMore && !loading" class="file-list__more">
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
	name: 'FileList',

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

		fileName(item) {
			return item.messageParameters?.file?.name
				?? item.messageParameters?.object?.name
				?? 'Unknown file'
		},

		mimeIcon(item) {
			const mime = item.messageParameters?.file?.mimetype ?? ''
			if (mime.startsWith('image/')) return 'icon-image'
			if (mime.startsWith('video/')) return 'icon-video'
			if (mime.startsWith('audio/')) return 'icon-sound'
			if (mime.includes('pdf')) return 'icon-pdf'
			if (mime.includes('zip') || mime.includes('tar')) return 'icon-archive'
			return 'icon-file'
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
.file-list__items {
	list-style: none;
	margin: 0;
	padding: 0;
}

.file-list__item {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 10px 12px;
	border-radius: 8px;
	cursor: pointer;
	transition: background 0.1s;
}

.file-list__item:hover {
	background: var(--color-background-hover);
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
	width: 20px;
	height: 20px;
	opacity: 0.75;
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
.file-list__more {
	display: flex;
	justify-content: center;
	padding: 20px 0;
}
</style>
