/**
 * useSharedItems — composable for loading shared items of a given type,
 * with pagination and search support.
 */

import { ref, watch } from 'vue'
import { fetchSharedItems, fetchShareOverview, fetchMessages, extractUrls } from '../api/talk.js'

export function useSharedItems(tokenRef, objectTypeRef) {
	const items = ref([])
	const loading = ref(false)
	const loadingMore = ref(false)
	const error = ref(null)
	const hasMore = ref(false)
	const cursor = ref(null)

	// For link extraction: track if we've scanned the full history
	const linkScanDone = ref(false)
	const linkMessages = ref([]) // raw messages containing URLs

	async function load() {
		const token = tokenRef.value
		const objectType = objectTypeRef.value
		if (!token || !objectType) return

		loading.value = true
		error.value = null
		items.value = []
		cursor.value = null
		hasMore.value = false
		linkScanDone.value = false
		linkMessages.value = []

		try {
			if (objectType === 'overview') {
				const data = await fetchShareOverview(token)
				// Flatten overview into a list with type annotation
				items.value = Object.entries(data).flatMap(([type, msgs]) =>
					msgs.map(m => ({ ...m, _overviewType: type })),
				)
			} else if (objectType === 'links') {
				await loadLinks(token)
			} else {
				const result = await fetchSharedItems(token, objectType, null)
				items.value = result.items
				cursor.value = result.lastKnownMessageId
				hasMore.value = cursor.value !== null
			}
		} catch (err) {
			error.value = err?.response?.data?.ocs?.meta?.message
				?? err?.message
				?? 'Failed to load items'
		} finally {
			loading.value = false
		}
	}

	async function loadMore() {
		const token = tokenRef.value
		const objectType = objectTypeRef.value
		if (!token || !objectType || !hasMore.value || loadingMore.value) return

		loadingMore.value = true
		try {
			if (objectType === 'links') {
				await loadLinksPage(token)
			} else {
				const result = await fetchSharedItems(token, objectType, cursor.value)
				items.value = [...items.value, ...result.items]
				cursor.value = result.lastKnownMessageId
				hasMore.value = cursor.value !== null
			}
		} catch (err) {
			error.value = err?.message ?? 'Failed to load more items'
		} finally {
			loadingMore.value = false
		}
	}

	// ── Link extraction ────────────────────────────────────────────────────────

	async function loadLinks(token) {
		// Fetch the first page of message history and start accumulating links
		linkMessages.value = []
		cursor.value = null
		await loadLinksPage(token)
	}

	async function loadLinksPage(token) {
		const result = await fetchMessages(token, cursor.value)

		// Extract links from comment messages only
		const newLinks = result.messages
			.filter(m => m.messageType === 'comment' && !m.systemMessage)
			.flatMap(m => {
				const urls = extractUrls(m.message)
				return urls.map(url => ({
					id: `${m.id}-${url}`,
					messageId: m.id,
					timestamp: m.timestamp,
					actorDisplayName: m.actorDisplayName,
					url,
					// Try to derive a display title from the message text
					title: m.message.trim() !== url ? m.message.trim() : url,
				}))
			})

		items.value = [...items.value, ...newLinks]
		cursor.value = result.lastKnownMessageId
		hasMore.value = !result.done
		if (result.done) {
			linkScanDone.value = true
		}
	}

	// Reload whenever token or objectType changes
	watch([tokenRef, objectTypeRef], () => {
		load()
	}, { immediate: false })

	return {
		items,
		loading,
		loadingMore,
		error,
		hasMore,
		linkScanDone,
		load,
		loadMore,
	}
}
