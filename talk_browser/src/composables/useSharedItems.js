/**
 * useSharedItems — composable for loading shared items of a given type,
 * with pagination and search support.
 */

import { ref, watch } from 'vue'
import { fetchSharedItems, fetchMessages, extractUrls } from '../api/talk.js'

export function useSharedItems(tokenRef, objectTypeRef) {
	const items = ref([])
	const loading = ref(false)
	const loadingMore = ref(false)
	const error = ref(null)
	const hasMore = ref(false)
	const cursor = ref(null)

	// For link extraction: track if we've scanned the full history
	const linkScanDone = ref(false)
	// Map<url, item> — source of truth for deduplication across pages
	const linkMap = ref(new Map())

	async function load() {
		const token = tokenRef.value
		const objectType = objectTypeRef.value
		// 'overview' is handled separately in App.vue via fetchShareOverview; skip here.
		if (!token || !objectType || objectType === 'overview') return

		loading.value = true
		error.value = null
		items.value = []
		cursor.value = null
		hasMore.value = false
		linkScanDone.value = false
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
		linkMap.value = new Map()
		cursor.value = null
		await loadLinksPage(token)
	}

	async function loadLinksPage(token) {
		const result = await fetchMessages(token, cursor.value)

		// Deduplicate by URL using a Map; first occurrence = newest (scan is newest→oldest)
		for (const m of result.messages) {
			if (m.messageType !== 'comment' || m.systemMessage) continue
			for (const url of extractUrls(m.message)) {
				if (linkMap.value.has(url)) {
					// Already seen — just bump the count
					const existing = linkMap.value.get(url)
					linkMap.value.set(url, { ...existing, count: existing.count + 1 })
				} else {
					linkMap.value.set(url, {
					id: `link-${url}`,
					messageId: m.id,
					timestamp: m.timestamp,
					actorDisplayName: m.actorDisplayName,
					url,
					// Use message text as title only if it adds something beyond the URL itself;
					// strip the URL from the text and truncate to avoid social-engineering abuse (F-14)
					title: (() => {
						const stripped = m.message.trim().replace(url, '').trim()
						return stripped.length > 0 ? stripped.slice(0, 150) : url
					})(),
					count: 1,
				})
				}
			}
		}

		// Re-derive items array from the map (insertion order = newest first)
		items.value = Array.from(linkMap.value.values())
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
