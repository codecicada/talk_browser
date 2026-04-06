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

// Maximum number of message pages to scan per load/scanMore call.
// Each page is 200 messages, so 3 pages covers ~600 messages.
const MAX_LINK_SCAN_PAGES = 3

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

	// AbortController for cancelling in-flight fetchMessages requests.
	let abortController = null

	async function load() {
		const token = tokenRef.value
		if (!token || !objectType || objectType === 'overview') return

		// Already loaded or currently loading — don't reload
		if (loading.value || loaded.value) return

		// Abort any previous in-flight scan
		if (abortController) {
			abortController.abort()
			abortController = null
		}

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
			if (err?.name === 'AbortError' || err?.code === 'ERR_CANCELED') return
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

	/** Resume scanning from the stored cursor, bounded by MAX_LINK_SCAN_PAGES. */
	async function scanMore() {
		const token = tokenRef.value
		if (!token || !hasMore.value || loadingMore.value) return

		// Abort any previous in-flight scan
		if (abortController) {
			abortController.abort()
			abortController = null
		}

		loadingMore.value = true
		hasMore.value = false
		try {
			await loadLinks(token, /* isMore= */ true)
		} catch (err) {
			if (err?.name === 'AbortError' || err?.code === 'ERR_CANCELED') return
			error.value = err?.message ?? 'Failed to scan more links'
		} finally {
			loadingMore.value = false
		}
	}

	/** Reset so the next load() call re-fetches from scratch. */
	function reset() {
		if (abortController) {
			abortController.abort()
			abortController = null
		}
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

	/**
	 * Scan up to MAX_LINK_SCAN_PAGES pages of message history for URLs.
	 * @param {string} token
	 * @param {boolean} isMore - if true, resumes from cursor without resetting the link map
	 */
	async function loadLinks(token, isMore = false) {
		abortController = new AbortController()
		const signal = abortController.signal

		if (!isMore) {
			linkMap.value = new Map()
			cursor.value = null
		}

		let pagesScanned = 0

		while (pagesScanned < MAX_LINK_SCAN_PAGES) {
			const result = await fetchMessages(token, cursor.value, 200, signal)

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
			pagesScanned++

			if (result.done) {
				// Reached beginning of history — no more pages available
				hasMore.value = false
				return
			}
		}

		// Stopped due to page limit, not end of history — more pages available
		hasMore.value = cursor.value !== null
	}

	return {
		items,
		loading,
		loadingMore,
		error,
		hasMore,
		load,
		loadMore,
		scanMore,
		reset,
	}
}
