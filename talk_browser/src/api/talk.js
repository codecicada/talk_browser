/**
 * Talk OCS API helpers.
 *
 * All requests use @nextcloud/axios which automatically injects the
 * Nextcloud CSRF token (RequestToken) header — no manual auth needed
 * because the user is already logged in via the Nextcloud session.
 */

import axios from '@nextcloud/axios'
import { getRootUrl } from '@nextcloud/router'

const OCS_BASE = '/ocs/v2.php/apps/spreed/api'

/**
 * Build an OCS URL using the Nextcloud root (not generateUrl which adds /index.php/).
 * getRootUrl() returns the Nextcloud base path (e.g. '' or '/nextcloud').
 * @param {string} path - path after /ocs/v2.php/apps/spreed/api
 */
function ocsUrl(path) {
	return `${getRootUrl()}${OCS_BASE}${path}`
}

/**
 * Default headers required by every OCS request.
 */
const OCS_HEADERS = {
	'OCS-APIRequest': 'true',
	Accept: 'application/json',
}

// ─── Conversations ────────────────────────────────────────────────────────────

/**
 * Fetch all conversations the current user is part of.
 * @returns {Promise<Array>} array of conversation objects
 */
export async function fetchConversations() {
	const response = await axios.get(ocsUrl('/v4/room'), { headers: OCS_HEADERS })
	return response.data.ocs.data
}

/**
 * Fetch (or create) the Note to Self conversation.
 * @returns {Promise<Object>} conversation object with .token
 */
export async function fetchNoteToSelf() {
	const response = await axios.get(ocsUrl('/v4/room/note-to-self'), { headers: OCS_HEADERS })
	return response.data.ocs.data
}

// ─── Shared items (files, media, audio, etc.) ────────────────────────────────

/**
 * Fetch a grouped overview of all shared item types in a conversation.
 * Requires the `rich-object-list-media` capability.
 *
 * @param {string} token  - conversation token
 * @param {number} limit  - max items per type (default 7 for overview)
 * @returns {Promise<Object>} object keyed by objectType
 */
export async function fetchShareOverview(token, limit = 7) {
	const response = await axios.get(
		ocsUrl(`/v1/chat/${token}/share/overview`),
		{ headers: OCS_HEADERS, params: { limit } },
	)
	return response.data.ocs.data
}

/**
 * Fetch shared items of a specific type, with cursor pagination.
 *
 * @param {string} token           - conversation token
 * @param {string} objectType      - media | file | audio | voice | recording | location | deckcard | other
 * @param {number|null} lastKnownMessageId - for pagination (null = start from newest)
 * @param {number} limit           - page size (max 200)
 * @returns {Promise<{items: Array, lastKnownMessageId: number|null}>}
 */
export async function fetchSharedItems(token, objectType, lastKnownMessageId = null, limit = 50) {
	const params = { limit }
	if (lastKnownMessageId !== null) {
		params.lastKnownMessageId = lastKnownMessageId
	}

	const response = await axios.get(
		ocsUrl(`/v1/chat/${token}/share`),
		{ headers: OCS_HEADERS, params: { objectType, ...params } },
	)

	const nextCursor = response.headers['x-chat-last-given']
		? parseInt(response.headers['x-chat-last-given'], 10)
		: null

	// The Talk share API returns an object keyed by message ID (ascending).
	// Reverse so newest items come first.
	const raw = response.data.ocs.data
	const items = Array.isArray(raw) ? raw.slice().reverse() : Object.values(raw).reverse()

	return {
		items,
		lastKnownMessageId: nextCursor,
	}
}

// ─── Full chat history (for link extraction) ─────────────────────────────────

/**
 * Fetch a page of chat history. Used for extracting plain-text URLs.
 *
 * @param {string} token
 * @param {number|null} lastKnownMessageId - cursor (null = newest page)
 * @param {number} limit
 * @returns {Promise<{messages: Array, lastKnownMessageId: number|null, done: boolean}>}
 */
export async function fetchMessages(token, lastKnownMessageId = null, limit = 200) {
	const params = {
		lookIntoFuture: 0,
		limit,
		setReadMarker: 0,
		noStatusUpdate: 1,
	}
	if (lastKnownMessageId !== null) {
		params.lastKnownMessageId = lastKnownMessageId
	}

	let response
	try {
		response = await axios.get(
			ocsUrl(`/v1/chat/${token}`),
			{ headers: OCS_HEADERS, params },
		)
	} catch (error) {
		// 304 Not Modified = no more history
		if (error.response?.status === 304) {
			return { messages: [], lastKnownMessageId: null, done: true }
		}
		throw error
	}

	const nextCursor = response.headers['x-chat-last-given']
		? parseInt(response.headers['x-chat-last-given'], 10)
		: null

	return {
		messages: response.data.ocs.data,
		lastKnownMessageId: nextCursor,
		done: nextCursor === null,
	}
}

// ─── URL extraction ───────────────────────────────────────────────────────────

const URL_REGEX = /https?:\/\/[^\s<>"{}|\\^`[\]]+/gi

/**
 * Extract all URLs from a plain-text comment message.
 * Returns deduplicated array of URL strings.
 *
 * @param {string} text
 * @returns {string[]}
 */
export function extractUrls(text) {
	const matches = text.match(URL_REGEX) ?? []
	// Strip trailing punctuation that is typically not part of a URL
	// (e.g. "see https://example.com." or "visit https://example.com)")
	const cleaned = matches.map(u => u.replace(/[.,;:!?)'">]+$/, ''))
	return [...new Set(cleaned)]
}
