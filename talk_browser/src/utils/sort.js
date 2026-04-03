/**
 * Sort shared items by a combined key-direction string.
 *
 * @param {Array}    items  - raw items array
 * @param {string}   sort   - e.g. 'date-desc' | 'name-asc' | 'size-desc' | 'count-desc'
 * @param {Function} getName - (item) => string  used for 'name' sorts
 * @returns {Array} new sorted array (original is not mutated)
 */
export function sortItems(items, sort, getName) {
	if (!sort || !items.length) return items

	const [key, dir] = sort.split('-')
	const asc = dir === 'asc'

	return [...items].sort((a, b) => {
		let va, vb

		switch (key) {
		case 'date':
			va = a.timestamp ?? 0
			vb = b.timestamp ?? 0
			break
		case 'name':
			va = (getName ? getName(a) : '').toLowerCase()
			vb = (getName ? getName(b) : '').toLowerCase()
			break
		case 'size':
			va = a.messageParameters?.file?.size ?? 0
			vb = b.messageParameters?.file?.size ?? 0
			break
		case 'count':
			va = a.count ?? 0
			vb = b.count ?? 0
			break
		default:
			return 0
		}

		if (va < vb) return asc ? -1 : 1
		if (va > vb) return asc ? 1 : -1
		return 0
	})
}
