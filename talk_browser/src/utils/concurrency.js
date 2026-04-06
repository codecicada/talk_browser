/**
 * A simple promise-based concurrency limiter.
 *
 * Enqueues async tasks and ensures no more than `maxConcurrent` tasks run
 * simultaneously. Queued tasks are processed in FIFO order as slots free up.
 *
 * @example
 * const limiter = new ConcurrencyLimiter(4)
 * limiter.enqueue(() => fetchOgMeta(url)).then(meta => ...)
 */
export class ConcurrencyLimiter {
	constructor(maxConcurrent) {
		this._max = maxConcurrent
		this._running = 0
		this._queue = [] // { fn, resolve, reject }[]
	}

	/**
	 * Enqueue an async function. Returns a Promise that resolves/rejects with
	 * the result of fn() once a concurrency slot is available.
	 *
	 * @param {() => Promise<any>} fn
	 * @returns {Promise<any>}
	 */
	enqueue(fn) {
		return new Promise((resolve, reject) => {
			this._queue.push({ fn, resolve, reject })
			this._drain()
		})
	}

	/**
	 * Clear all pending (not yet started) tasks from the queue.
	 * Already-running tasks are left to complete naturally.
	 */
	clear() {
		this._queue = []
	}

	_drain() {
		while (this._running < this._max && this._queue.length > 0) {
			const { fn, resolve, reject } = this._queue.shift()
			this._running++
			Promise.resolve()
				.then(() => fn())
				.then(
					(value) => {
						this._running--
						resolve(value)
						this._drain()
					},
					(err) => {
						this._running--
						reject(err)
						this._drain()
					},
				)
		}
	}
}
