/**
 * URL safety helpers.
 *
 * All values that originate from the Talk API or from message text must be
 * validated before they are used as href values, window.open targets, or
 * embedded in <audio>/<video> src attributes.
 */

import { getRootUrl } from '@nextcloud/router'

/**
 * Validate that a URL uses an allowed scheme (http or https).
 * Returns the original URL string if safe, or null otherwise.
 *
 * Use this for any API-supplied URL that will be placed in an <a href>,
 * a window.open() call, or any other navigation target.
 *
 * @param {string|null|undefined} url
 * @returns {string|null}
 */
export function safeUrl(url) {
	if (!url) return null
	try {
		const { protocol } = new URL(url)
		return (protocol === 'https:' || protocol === 'http:') ? url : null
	} catch {
		return null
	}
}

/**
 * Build a safe WebDAV streaming URL from a Nextcloud file path.
 *
 * Guards against:
 * - Missing or empty path
 * - Path traversal (any ".." or "." segment)
 * - The resulting URL drifting off the expected origin
 *
 * Each path segment is individually encodeURIComponent-encoded so that
 * "/" separators are preserved while special characters within segment
 * names are escaped.
 *
 * @param {string|null|undefined} filePath  - e.g. "admin/files/photo.jpg"
 * @returns {string}  Safe WebDAV URL, or empty string on failure
 */
export function safeWebdavUrl(filePath) {
	if (!filePath) return ''
	const segments = filePath.split('/')
	// Reject any traversal attempts
	if (segments.some(seg => seg === '..' || seg === '.')) return ''
	const encoded = segments.map(encodeURIComponent).join('/')
	const root = getRootUrl()
	const url = `${root}/remote.php/webdav/${encoded}`
	// Ensure the result stays on the expected origin
	if (!url.startsWith(root + '/remote.php/webdav/')) return ''
	return url
}

/**
 * Return a safe integer string for use in a Nextcloud preview URL query param.
 * Rejects any value that is not a finite non-negative integer.
 *
 * @param {string|number|null|undefined} id
 * @returns {string|null}
 */
export function safeFileId(id) {
	if (id === null || id === undefined || id === '') return null
	const n = parseInt(id, 10)
	if (!Number.isFinite(n) || n < 0 || String(n) !== String(id).trim()) return null
	return String(n)
}
