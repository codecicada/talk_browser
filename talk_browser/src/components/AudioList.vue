<template>
	<div class="audio-list">
		<NcEmptyContent
			v-if="!loading && filtered.length === 0"
			:name="emptyTitle"
			:description="emptyDescription"
		>
			<template #icon>
				<span :class="isVoice ? 'icon-microphone' : 'icon-sound'" />
			</template>
		</NcEmptyContent>

		<ul v-else class="audio-list__items">
			<li
				v-for="item in filtered"
				:key="item.id"
				:data-id="item.id"
				class="audio-list__item"
			>
				<!-- Inline HTML5 audio player -->
				<div class="audio-list__player-wrap">
					<audio
						v-if="!brokenSrc[item.id]"
						controls
						preload="none"
						:src="audioSrc(item)"
						:aria-label="fileName(item)"
						class="audio-list__player"
						@error="markBroken(item.id)"
					/>
					<span v-else class="audio-list__broken">
						<span :class="isVoice ? 'icon-microphone' : 'icon-sound'" aria-hidden="true" />
						{{ t('talk_browser', 'Audio unavailable') }}
					</span>
				</div>

				<div class="audio-list__info">
					<span class="audio-list__name">{{ fileName(item) }}</span>
					<span class="audio-list__meta">
						{{ item.actorDisplayName }}
						&middot;
						{{ formatDate(item.timestamp) }}
					</span>
				</div>

				<a
					v-if="fileLink(item)"
					:href="fileLink(item)"
					target="_blank"
					rel="noopener noreferrer"
					class="audio-list__download"
					:aria-label="t('talk_browser', 'Open {name} in Files (opens in new tab)', { name: fileName(item) })"
				>
					<span class="icon-external" aria-hidden="true" />
				</a>
			</li>
		</ul>

		<div v-if="loading" class="audio-list__loading" role="status" aria-live="polite">
			<NcLoadingIcon :size="32" aria-hidden="true" />
			<span class="sr-only">{{ t('talk_browser', 'Loading audio…') }}</span>
		</div>

		<div v-if="loadingMore" class="audio-list__loading-more" role="status" aria-live="polite">
			<NcLoadingIcon :size="24" aria-hidden="true" />
			<span class="audio-list__loading-note">
				{{ t('talk_browser', 'Loading more…') }}
			</span>
		</div>

		<div v-if="hasMore && !loading && !loadingMore" class="audio-list__more">
			<NcButton @click="$emit('load-more')">
				{{ t('talk_browser', 'Load more') }}
			</NcButton>
		</div>
	</div>
</template>

<script>
import { NcButton, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import { safeUrl, safeWebdavUrl } from '../utils/url.js'
import useListBehavior from '../composables/useListBehavior.js'

export default {
	name: 'AudioList',

	components: { NcButton, NcEmptyContent, NcLoadingIcon },

	mixins: [useListBehavior],

	props: {
		// true = voice notes (voice type), false = audio files (audio type)
		isVoice: { type: Boolean, default: false },
	},

	data() {
		return {
			brokenSrc: {},
		}
	},

	computed: {
		listBehaviorOptions() {
			const isVoice = this.isVoice
			return {
				highlightClass: 'audio-list__item--highlight',
				getItemName(item) {
					return item.messageParameters?.file?.name ?? 'Audio'
				},
				getSearchFields(item) {
					const name = item.messageParameters?.file?.name ?? 'Audio'
					return [name, item.actorDisplayName ?? '']
				},
				emptyNoun: isVoice ? 'voice notes' : 'audio files',
				emptyAction: isVoice
					? t('talk_browser', 'Record a voice note in Talk to see it here')
					: t('talk_browser', 'Share an audio file in this conversation to see it here'),
			}
		},
	},

	methods: {
		t,

		/** Alias for template compatibility */
		fileName(item) {
			return this.getItemName(item)
		},

		markBroken(id) {
			this.$set(this.brokenSrc, id, true)
		},

		fileLink(item) {
			return safeUrl(item.messageParameters?.file?.link ?? null)
		},

		audioSrc(item) {
			return safeWebdavUrl(item.messageParameters?.file?.path)
		},
	},
}
</script>

<style scoped>
.audio-list__items {
	list-style: none;
	margin: 0;
	padding: 0;
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.audio-list__item {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 10px 12px;
	border-radius: 8px;
	border: 1px solid var(--color-border);
	background: var(--color-background-dark);
}

.audio-list__broken {
	display: flex;
	align-items: center;
	gap: 6px;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	opacity: 0.7;
	min-width: 200px;
}

.audio-list__item--highlight {
	outline: 2px solid var(--color-primary-element);
	animation: tb-highlight-fade 2s ease forwards;
}

.audio-list__player-wrap {
	flex-shrink: 0;
}

.audio-list__player {
	height: 36px;
	min-width: 200px;
	max-width: 320px;
}

.audio-list__info {
	flex: 1;
	min-width: 0;
}

.audio-list__name {
	display: block;
	font-weight: 500;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.audio-list__meta {
	display: block;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.audio-list__download {
	flex-shrink: 0;
	opacity: 0.5;
	transition: opacity 0.1s;
}

.audio-list__download:hover {
	opacity: 1;
}

.audio-list__loading,
.audio-list__loading-more,
.audio-list__more {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 8px;
	justify-content: center;
	padding: 20px 0;
}

.audio-list__more {
	flex-direction: row;
}

.audio-list__loading-note {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}
</style>
