<template>
	<div class="conversation-list">
		<div v-if="loading" class="conversation-list__loading" role="status" aria-live="polite">
			<NcLoadingIcon :size="20" aria-hidden="true" />
			<span class="sr-only">{{ t('talk_browser', 'Loading conversations…') }}</span>
		</div>

		<template v-else>
			<NcTextField
				v-model="search"
				:label="t('talk_browser', 'Search conversations')"
				:placeholder="t('talk_browser', 'Search…')"
				class="conversation-list__search"
				trailing-button-icon="close"
				:show-trailing-button="search !== ''"
				@trailing-button-click="search = ''"
			/>

			<NcEmptyContent
				v-if="filtered.length === 0"
				:name="t('talk_browser', 'No conversations found')"
			>
				<template #icon>
					<span class="icon-search" aria-hidden="true" />
				</template>
			</NcEmptyContent>

			<ul
				v-else
				class="conversation-list__items"
				role="listbox"
				:aria-label="t('talk_browser', 'Conversations')"
			>
				<li
					v-for="conv in filtered"
					:key="conv.token"
					role="option"
					:aria-selected="conv.token === value"
					tabindex="0"
					:class="[
						'conversation-list__item',
						{ 'conversation-list__item--active': conv.token === value },
					]"
					@click="$emit('input', conv.token)"
					@keydown.enter.prevent="$emit('input', conv.token)"
					@keydown.space.prevent="$emit('input', conv.token)"
				>
					<!-- Avatar: user avatar for 1:1; current user for note-to-self; room avatar for group/public -->
					<NcAvatar
						v-if="conv.type === 1"
						:user="conv.name"
						:display-name="conv.displayName"
						:size="32"
						:show-user-status="false"
						class="conversation-list__avatar"
					/>
					<NcAvatar
						v-else-if="conv.type === 6"
						:user="currentUser.uid"
						:display-name="currentUser.displayName"
						:size="32"
						:show-user-status="false"
						class="conversation-list__avatar"
					/>
					<NcAvatar
						v-else
						:url="roomAvatarUrl(conv)"
						:display-name="conv.displayName"
						:size="32"
						:is-no-user="true"
						class="conversation-list__avatar"
					/>

					<!-- Name + type badge -->
					<span class="conversation-list__name">{{ conv.displayName }}</span>
					<span v-if="conv.type === 6" class="conversation-list__badge">
						{{ t('talk_browser', 'You') }}
					</span>
				</li>
			</ul>
		</template>
	</div>
</template>

<script>
import { NcAvatar, NcEmptyContent, NcLoadingIcon, NcTextField } from '@nextcloud/vue'
import { getRootUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'
import { getCurrentUser } from '@nextcloud/auth'

export default {
	name: 'ConversationPicker',

	components: { NcAvatar, NcEmptyContent, NcLoadingIcon, NcTextField },

	props: {
		conversations: {
			type: Array,
			default: () => [],
		},
		value: {
			type: String,
			default: null,
		},
		loading: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			currentUser: getCurrentUser() || { uid: '', displayName: '' },
			search: '',
		}
	},

	computed: {
		filtered() {
			if (!this.search) return this.conversations
			const q = this.search.toLowerCase()
			return this.conversations.filter(c =>
				c.displayName?.toLowerCase().includes(q),
			)
		},
	},

	methods: {
		t,

		/**
		 * Avatar URL for group / public conversations.
		 * Uses the Talk room avatar endpoint (app route, not OCS).
		 * Appends avatarVersion as a cache-buster when available.
		 */
		roomAvatarUrl(conv) {
			const base = `${getRootUrl()}/ocs/v2.php/apps/spreed/api/v1/room/${encodeURIComponent(conv.token)}/avatar`
			return conv.avatarVersion
				? `${base}?v=${encodeURIComponent(conv.avatarVersion)}`
				: base
		},
	},
}
</script>

<style scoped>
.conversation-list {
	width: 100%;
}

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

.conversation-list__loading {
	display: flex;
	justify-content: center;
	padding: 16px 0;
}

.conversation-list__search {
	margin: 4px 8px 8px;
}

.conversation-list__items {
	list-style: none;
	margin: 0;
	padding: 0 4px;
}

.conversation-list__item {
	display: flex;
	align-items: center;
	gap: 10px;
	padding: 8px 10px;
	border-radius: 8px;
	cursor: pointer;
	transition: background 0.15s;
	min-width: 0;
}

.conversation-list__item:hover {
	background: var(--color-background-hover);
}

.conversation-list__item--active {
	background: var(--color-primary-element-light);
	font-weight: 600;
}

.conversation-list__item--active:hover {
	background: var(--color-primary-element-light-hover, var(--color-primary-element-light));
}

.conversation-list__avatar {
	flex-shrink: 0;
}

.conversation-list__name {
	flex: 1;
	min-width: 0;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	font-size: 14px;
}

.conversation-list__badge {
	flex-shrink: 0;
	font-size: 11px;
	padding: 1px 6px;
	border-radius: 10px;
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
	font-weight: 600;
}
</style>
