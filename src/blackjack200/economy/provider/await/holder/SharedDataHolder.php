<?php

namespace blackjack200\economy\provider\await\holder;

use blackjack200\economy\provider\UpdateResult;
use Closure;
use Generator;

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