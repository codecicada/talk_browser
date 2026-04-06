/**
 * Shared item type definitions.
 * Each tab maps to an objectType from the Talk share API,
 * or to a special 'links' type that is extracted client-side.
 *
 * NOTE: All string labels (TABS[].label, SORT_OPTIONS[*][].label) are plain
 * strings here and wrapped with t() at render time. To ensure the extraction
 * tool (translationtool.phar) discovers them, explicit t() calls for every
 * label must be kept in sync in src/l10n-strings.js.
 */

/**
 * Available sort option groups.
 * Keys are referenced by TABS[].sortKeys.
 */
export const SORT_OPTIONS = {
	date: [
		{ value: 'date-desc', label: 'Newest first' },
		{ value: 'date-asc', label: 'Oldest first' },
	],
	name: [
		{ value: 'name-asc', label: 'Name A→Z' },
		{ value: 'name-desc', label: 'Name Z→A' },
	],
	size: [
		{ value: 'size-desc', label: 'Largest first' },
		{ value: 'size-asc', label: 'Smallest first' },
	],
	count: [
		{ value: 'count-desc', label: 'Most shared' },
		{ value: 'count-asc', label: 'Least shared' },
	],
}

// IMPORTANT: Keep tab IDs in sync with $tabPattern in appinfo/routes.php
export const TABS = [
	{
		id: 'overview',
		label: 'Overview',
		objectType: null, // special: uses /share/overview endpoint
		icon: 'icon-home',
		sortKeys: [],
	},
	{
		id: 'media',
		label: 'Images & Video',
		objectType: 'media',
		icon: 'icon-picture',
		sortKeys: ['date', 'name'],
	},
	{
		id: 'file',
		label: 'Files',
		objectType: 'file',
		icon: 'icon-files-dark',
		sortKeys: ['date', 'name', 'size'],
	},
	{
		id: 'audio',
		label: 'Audio',
		objectType: 'audio',
		icon: 'icon-sound',
		sortKeys: ['date', 'name'],
	},
	{
		id: 'voice',
		label: 'Voice notes',
		objectType: 'voice',
		icon: 'icon-microphone',
		sortKeys: ['date', 'name'],
	},
	{
		id: 'links',
		label: 'Links',
		objectType: 'links', // handled specially
		icon: 'icon-link',
		sortKeys: ['date', 'name', 'count'],
	},
	{
		id: 'location',
		label: 'Locations',
		objectType: 'location',
		icon: 'icon-address',
		sortKeys: ['date', 'name'],
	},
	{
		id: 'other',
		label: 'Other',
		objectType: 'other',
		icon: 'icon-more',
		sortKeys: ['date', 'name'],
	},
]

/**
 * Conversation type constants from Talk API.
 */
export const CONVERSATION_TYPE = {
	ONE_TO_ONE: 1,
	GROUP: 2,
	PUBLIC: 3,
	CHANGELOG: 4,
	FORMER_ONE_TO_ONE: 5,
	NOTE_TO_SELF: 6,
}
