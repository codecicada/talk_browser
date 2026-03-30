<template>
	<div class="conversation-list">
		<div v-if="loading" class="conversation-list__loading">
			<NcLoadingIcon :size="20" />
		</div>

		<ul v-else class="conversation-list__items">
			<li
				v-for="conv in conversations"
				:key="conv.token"
				:class="[
					'conversation-list__item',
					{ 'conversation-list__item--active': conv.token === value },
				]"
				@click="$emit('input', conv.token)"
			>
				<!-- Avatar / icon -->
				<span class="conversation-list__avatar" aria-hidden="true">
					<span v-if="conv.type === 6" class="icon-user-admin conversation-list__avatar-icon" />
					<span v-else-if="conv.type === 2 || conv.type === 3" class="icon-contacts conversation-list__avatar-icon" />
					<span v-else class="icon-user conversation-list__avatar-icon" />
				</span>

				<!-- Name + type badge -->
				<span class="conversation-list__name">{{ conv.displayName }}</span>
				<span v-if="conv.type === 6" class="conversation-list__badge">
					{{ t('talk_browser', 'You') }}
				</span>
			</li>
		</ul>
	</div>
</template>

<script>
import { NcLoadingIcon } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'ConversationPicker',

	components: { NcLoadingIcon },

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

	methods: { t },
}
</script>

<style scoped>
.conversation-list {
	width: 100%;
}

.conversation-list__loading {
	display: flex;
	justify-content: center;
	padding: 16px 0;
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
	width: 32px;
	height: 32px;
	border-radius: 50%;
	background: var(--color-background-dark);
	display: flex;
	align-items: center;
	justify-content: center;
}

.conversation-list__avatar-icon {
	width: 16px;
	height: 16px;
	opacity: 0.7;
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
