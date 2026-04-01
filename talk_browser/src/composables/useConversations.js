/**
 * useConversations — composable for loading and selecting conversations.
 */

import { ref, computed } from 'vue'
import { fetchConversations, fetchNoteToSelf } from '../api/talk.js'
import { CONVERSATION_TYPE } from '../constants.js'

export function useConversations() {
	const conversations = ref([])
	const loading = ref(false)
	const error = ref(null)
	const selectedToken = ref(null)

	const selectedConversation = computed(() =>
		conversations.value.find(c => c.token === selectedToken.value) ?? null,
	)

	/**
	 * Load all conversations and pre-select the preferred token when given,
	 * otherwise default to Note to Self.
	 * @param {string|null} preferredToken  token from the URL hash (optional)
	 */
	async function load(preferredToken = null) {
		loading.value = true
		error.value = null
		try {
			// Load Note to Self first so we always have its token
			const noteToSelf = await fetchNoteToSelf()

			// Load all conversations
			const all = await fetchConversations()

			// Ensure Note to Self is first in the list
			const withoutNts = all.filter(c => c.type !== CONVERSATION_TYPE.NOTE_TO_SELF)
			conversations.value = [noteToSelf, ...withoutNts]

			// Restore from URL hash if the token exists in the list, else fall back to Note to Self
			const allConversations = conversations.value
			const preferred = preferredToken && allConversations.find(c => c.token === preferredToken)
			selectedToken.value = preferred ? preferred.token : noteToSelf.token
		} catch (err) {
			if (process.env.NODE_ENV !== 'production') {
				// eslint-disable-next-line no-console
				console.warn('[talk_browser] loadConversations error:', err)
			}
			error.value = err?.response?.data?.ocs?.meta?.message
				?? err?.message
				?? 'Failed to load conversations'
		} finally {
			loading.value = false
		}
	}

	return {
		conversations,
		loading,
		error,
		selectedToken,
		selectedConversation,
		load,
	}
}
