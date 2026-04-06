import { translate as t } from '@nextcloud/l10n'
import { sortItems } from '../utils/sort.js'

/**
 * useListBehavior — Vue 2 mixin providing shared list logic for all Talk Browser
 * list components: filtering, sorting, scrolling, date formatting, highlight,
 * and empty state generation.
 *
 * Each consuming component should define a `listBehaviorOptions` data/computed
 * property with the following optional keys:
 *
 *   highlightClass {string}   — BEM class to add/remove for highlight animation
 *   getItemName(item) {fn}    — Returns display name string for an item
 *   getSearchFields(item) {fn}— Returns array of strings to search across (multi-field)
 *   emptyNoun {string}        — e.g. "files", "images or videos" (used in empty state)
 *   emptyAction {string}      — e.g. "Share a file in this conversation to see it here"
 */
const useListBehavior = {
	props: {
		items: { type: Array, default: () => [] },
		loading: { type: Boolean, default: false },
		loadingMore: { type: Boolean, default: false },
		hasMore: { type: Boolean, default: false },
		search: { type: String, default: '' },
		sort: { type: String, default: 'date-desc' },
		highlightId: { type: Number, default: null },
	},

	watch: {
		highlightId(id) {
			if (!id) return
			this.$nextTick(() => this.scrollToItem(id))
		},
	},

	mounted() {
		if (this.highlightId) {
			this.$nextTick(() => this.scrollToItem(this.highlightId))
		}
	},

	computed: {
		filtered() {
			const options = this.listBehaviorOptions || {}
			let result = this.items
			if (this.search) {
				const q = this.search.toLowerCase()
				result = result.filter(item => {
					if (options.getSearchFields) {
						return options.getSearchFields(item).some(
							field => (field || '').toLowerCase().includes(q),
						)
					}
					return this.getItemName(item).toLowerCase().includes(q)
				})
			}
			return sortItems(result, this.sort, item => this.getItemName(item))
		},

		emptyTitle() {
			if (this.search) {
				return t('talk_browser', 'No results for "{search}"', { search: this.search })
			}
			const options = this.listBehaviorOptions || {}
			const noun = options.emptyNoun || 'items'
			return t('talk_browser', 'No {noun} yet', { noun })
		},

		emptyDescription() {
			if (this.search) {
				return t('talk_browser', 'Try a different search term')
			}
			const options = this.listBehaviorOptions || {}
			return options.emptyAction || ''
		},
	},

	methods: {
		/**
		 * Returns the display name for an item.
		 * Overridable via listBehaviorOptions.getItemName.
		 */
		getItemName(item) {
			const options = this.listBehaviorOptions || {}
			if (options.getItemName) {
				return options.getItemName(item)
			}
			return item.messageParameters?.file?.name
				?? item.messageParameters?.object?.name
				?? 'Unknown'
		},

		/**
		 * Returns locale-formatted date string for a Unix timestamp.
		 */
		formatDate(timestamp) {
			return new Date(timestamp * 1000).toLocaleDateString(undefined, {
				year: 'numeric', month: 'short', day: 'numeric',
			})
		},

		/**
		 * Smoothly scrolls to the item with the given id (data-id attribute),
		 * then briefly applies the highlight class.
		 */
		scrollToItem(id) {
			const safeId = parseInt(id, 10)
			if (!Number.isFinite(safeId)) return
			const el = this.$el.querySelector(`[data-id="${safeId}"]`)
			if (!el) return
			el.scrollIntoView({ behavior: 'smooth', block: 'center' })
			const options = this.listBehaviorOptions || {}
			const highlightClass = options.highlightClass || 'item--highlight'
			el.classList.add(highlightClass)
			setTimeout(() => el.classList.remove(highlightClass), 2000)
		},
	},
}

export default useListBehavior
