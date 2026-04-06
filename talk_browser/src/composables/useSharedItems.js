/**
 * useSharedItems — composable for loading shared items of a single type.
 *
 * One instance is created per tab and kept alive for the session, so switching
 * tabs never re-fetches data that has already been loaded.
 *
 * @param {import('vue').Ref<string>} tokenRef      - reactive conversation token
 * @param {string}                    objectType     - tab type (media | file | audio | …)
 */

import { ref } from 'vue'
import { fetchSharedItems, fetchMessages, extractUrls } from '../api/talk.js'

export function useSharedItems(tokenRef, objectType) {
	const items = ref([])
	const loading = ref(false)
	const loadingMore = ref(false)
	const error = ref(null)
	const hasMore = ref(false)
	const cursor = ref(null)
	// True once a load attempt has completed (success or empty) — prevents
	// re-fetching tabs that genuinely have no data.
	const loaded = ref(false)

	// For link extraction
	const linkMap = ref(new Map())

	// Monotonically incrementing counter used to cancel in-progress loadLinks()
	// loops when reset() or a new load() is called. Each loop captures the value
	// at its start and bails out as soon as the counter moves ahead.
	// Plain let (non-reactive) — only internal loop logic reads it.
	let scanGeneration = 0

	async function load() {
		const token = tokenRef.value
		if (!token || !objectType || objectType === 'overview') return

		// Already loaded or currently loading — don't reload
		if (loading.value || loaded.value) return

		loading.value = true
		error.value = null
		items.value = []
		cursor.value = null
		hasMore.value = false
		linkMap.value = new Map()

		try {
			if (objectType === 'links') {
				await loadLinks(token)
			} else {
				const result = await fetchSharedItems(token, objectType, null)
				items.value = result.items
				cursor.value = result.lastKnownMessageId
				hasMore.value = cursor.value !== null
			}
		} catch (err) {
			if (process.env.NODE_ENV !== 'production') {
				// eslint-disable-next-line no-console
				console.warn('[talk_browser] loadItems error:', err)
			}
			error.value = err?.response?.data?.ocs?.meta?.message
				?? err?.message
				?? 'Failed to load items'
		} finally {
			loading.value = false
			loaded.value = true
		}
	}

	async function loadMore() {
		const token = tokenRef.value
		if (!token || !hasMore.value || loadingMore.value) return

		loadingMore.value = true
		try {
			const result = await fetchSharedItems(token, objectType, cursor.value)
			items.value = [...items.value, ...result.items]
			cursor.value = result.lastKnownMessageId
			hasMore.value = cursor.value !== null
		} catch (err) {
			error.value = err?.message ?? 'Failed to load more items'
		} finally {
			loadingMore.value = false
		}
	}

	/** Reset so the next load() call re-fetches from scratch. */
	function reset() {
		scanGeneration++ // cancel any in-progress loadLinks() loop
		items.value = []
		cursor.value = null
		hasMore.value = false
		error.value = null
		loading.value = false
		loadingMore.value = false
		loaded.value = false
		linkMap.value = new Map()
	}

	// ── Link extraction ────────────────────────────────────────────────────────

	async function loadLinks(token) {
		// Increment generation and capture — any older loop will see a mismatch
		// and exit without writing state.
		scanGeneration++
		const gen = scanGeneration

		linkMap.value = new Map()
		cursor.value = null

		// Scan all pages of chat history automatically, updating items reactively
		// after each page so links appear progressively while loading.
		let done = false
		while (!done) {
			// Guard: bail if a newer load/reset has superseded this loop
			if (gen !== scanGeneration) return

			const result = await fetchMessages(token, cursor.value)

			// Guard: discard results if cancelled while the fetch was in-flight
			if (gen !== scanGeneration) return

			for (const m of result.messages) {
				if (m.messageType !== 'comment' || m.systemMessage) continue
				for (const url of extractUrls(m.message)) {
					if (linkMap.value.has(url)) {
						const existing = linkMap.value.get(url)
						linkMap.value.set(url, { ...existing, count: existing.count + 1 })
					} else {
					linkMap.value.set(url, {
						id: `link-${url}`,
						messageId: m.id,
						conversationToken: token,
						timestamp: m.timestamp,
						actorDisplayName: m.actorDisplayName,
						url,
						title: (() => {
							const stripped = m.message.trim().replace(url, '').trim()
							return stripped.length > 0 ? stripped.slice(0, 150) : url
						})(),
						count: 1,
					})
					}
				}
			}

			// Update items reactively after each page so links appear as they're found
			items.value = Array.from(linkMap.value.values())
			cursor.value = result.lastKnownMessageId
			done = result.done
		}

		hasMore.value = false
	}

	return {
		items,
		loading,
		loadingMore,
		error,
		hasMore,
		load,
		loadMore,
		reset,
	}
}
