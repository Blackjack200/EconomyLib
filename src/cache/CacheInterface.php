<?php

namespace blackjack200\cache;

/**
 * @author blackjack200
 * @phpstan-template  T
 */
interface CacheInterface {
	/**
	 * @param T $val
	 * @return bool
	 */
	public function put(string $key, mixed $val) : bool;

	/**
	 * @return T
	 */
	public function get(string $key) : mixed;

	/**
	 * @return bool
	 */
	public function has(string $key) : bool;

	/** @return bool */
	public function clear(string $key) : bool;

	/** @return string[] */
	public function keys() : array;

	/** @return bool */
	public function clearAll() : bool;
}