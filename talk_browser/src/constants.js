/**
 * Shared item type definitions.
 * Each tab maps to an objectType from the Talk share API,
 * or to a special 'links' type that is extracted client-side.
 */

export const TABS = [
	{
		id: 'overview',
		label: 'Overview',
		objectType: null, // special: uses /share/overview endpoint
		icon: 'icon-home',
	},
	{
		id: 'media',
		label: 'Images & Video',
		objectType: 'media',
		icon: 'icon-picture',
	},
	{
		id: 'file',
		label: 'Files',
		objectType: 'file',
		icon: 'icon-files-dark',
	},
	{
		id: 'audio',
		label: 'Audio',
		objectType: 'audio',
		icon: 'icon-sound',
	},
	{
		id: 'voice',
		label: 'Voice notes',
		objectType: 'voice',
		icon: 'icon-microphone',
	},
	{
		id: 'links',
		label: 'Links',
		objectType: 'links', // handled specially
		icon: 'icon-link',
	},
	{
		id: 'location',
		label: 'Locations',
		objectType: 'location',
		icon: 'icon-address',
	},
	{
		id: 'other',
		label: 'Other',
		objectType: 'other',
		icon: 'icon-more',
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
