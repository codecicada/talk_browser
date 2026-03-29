<template>
	<NcSelect
		v-model="selected"
		:options="options"
		:loading="loading"
		:placeholder="t('talk_content_browser', 'Select a conversation…')"
		label="displayName"
		track-by="token"
		class="conversation-picker"
		@update:model-value="onChange"
	>
		<template #option="{ option }">
			<span class="conversation-picker__option">
				<span v-if="option.type === 6" class="conversation-picker__badge conversation-picker__badge--nts">
					{{ t('talk_content_browser', 'Note to self') }}
				</span>
				<span v-else class="conversation-picker__type">{{ typeLabel(option.type) }}</span>
				<span class="conversation-picker__name">{{ option.displayName }}</span>
			</span>
		</template>
	</NcSelect>
</template>

<script>
import { NcSelect } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import { conversationTypeLabel } from '../constants.js'

export default {
	name: 'ConversationPicker',

	components: { NcSelect },

	props: {
		conversations: {
			type: Array,
			required: true,
		},
		modelValue: {
			type: String,
			default: null,
		},
		loading: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['update:modelValue'],

	computed: {
		options() {
			return this.conversations
		},
		selected: {
			get() {
				return this.conversations.find(c => c.token === this.modelValue) ?? null
			},
			set(val) {
				this.$emit('update:modelValue', val?.token ?? null)
			},
		},
	},

	methods: {
		t,
		typeLabel: conversationTypeLabel,
		onChange(val) {
			this.$emit('update:modelValue', val?.token ?? null)
		},
	},
}
</script>

<style scoped>
.conversation-picker {
	min-width: 280px;
}

.conversation-picker__option {
	display: flex;
	align-items: center;
	gap: 6px;
}

.conversation-picker__badge {
	font-size: 11px;
	padding: 1px 5px;
	border-radius: 4px;
	font-weight: 600;
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
	white-space: nowrap;
}

.conversation-picker__type {
	font-size: 11px;
	color: var(--color-text-maxcontrast);
	white-space: nowrap;
}

.conversation-picker__name {
	font-weight: 500;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
</style>
