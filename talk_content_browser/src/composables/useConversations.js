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
	 * Load all conversations and pre-select Note to Self.
	 */
	async function load() {
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

			// Default selection: Note to Self
			selectedToken.value = noteToSelf.token
		} catch (err) {
			error.value = err?.response?.data?.ocs?.meta?.message
				?? err?.message
				?? 'Failed to load conversations'
		} finally {
			loading.value = false
		}
	}

	function selectConversation(token) {
		selectedToken.value = token
	}

	return {
		conversations,
		loading,
		error,
		selectedToken,
		selectedConversation,
		load,
		selectConversation,
	}
}
