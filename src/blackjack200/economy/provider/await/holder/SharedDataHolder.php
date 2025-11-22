<?php

namespace blackjack200\economy\provider\await\holder;

use blackjack200\economy\provider\UpdateResult;
use Closure;
use Generator;

/**
 * SharedDataHolder interface
 *
 * Provides a contract for a shared data storage system supporting both synchronous
 * and asynchronous operations. This interface abstracts the underlying storage
 * (e.g., database or in-memory cache) and allows implementations to optimize
 * access through caching strategies.
 *
 * # Methods
 *
 * - `get(string $key, bool $preferCache)`: Retrieve the value associated with a key.
 *   If `$preferCache` is true and a cached value exists, return the cached value.
 *   Otherwise, fetch the value from the database and optionally update the cache.
 *
 * - `readCached(string $key)`: Attempt to read a value from the cache only. Returns
 *   the cached value if present, otherwise `null`.
 *
 * - `set(string $key, mixed $value, bool $optimistic)`: Set a value for a key.
 *   The `$value` must be JSON-serializable. If `$optimistic` is true, the local cache
 *   is updated first; otherwise, the value is fetched from the database after
 *   each update to ensure it reflects the latest state. Returns either a generator
 *   or an `UpdateResult` indicating `SUCCESS`, `NO_CHANGE`, or `INTERNAL_ERROR`.
 *
 * - `update(string $key, Closure $operator, bool $optimistic)`: Atomically update
 *   a key's value using the provided operator function `$operator`. The operation
 *   guarantees atomicity. The `$optimistic` flag behaves as in `set()`.
 *   Returns a generator or `UpdateResult`.
 *
 * - `unset(string $key, bool $optimistic)`: Delete a key from the store. `$optimistic`
 *   determines whether the deletion is applied first locally or synced with the database.
 *   Returns a generator or `UpdateResult`.
 *
 * - `sync()`: Synchronize the local cache with the latest data from the database,
 *   overwriting any stale local values. Can return void or a generator.
 *
 * @see UpdateResult
 */
interface SharedDataHolder {

	public function get(string $key, bool $preferCache);

	public function readCached(string $key);

	/**
	 * @template T
	 * @param T $value
	 * @return Generator<void,mixed,void,bool>|UpdateResult
	 */
	public function set(string $key, $value, bool $optimistic);

	/**
	 * @template T
	 * @param Closure(T):T $operator
	 * @return Generator<void,mixed,void,bool>|UpdateResult
	 */
	public function update(string $key, Closure $operator, bool $optimistic);

	/**
	 * @return Generator<void,mixed,void,bool>|UpdateResult
	 */
	public function unset(string $key, bool $optimistic);

	/**
	 * @return Generator<void,mixed,void,void>|void
	 */
	public function sync();
}