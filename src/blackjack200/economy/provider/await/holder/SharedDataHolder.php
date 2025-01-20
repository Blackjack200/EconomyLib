<?php

namespace blackjack200\economy\provider\await\holder;

use Closure;
use Generator;

interface SharedDataHolder {

	public function get(string $key, bool $preferCache);

	public function readCached(string $key);

	/**
	 * @template T
	 * @param T $value
	 * @return Generator<void,void,mixed,bool>|bool
	 */
	public function set(string $key, $value, bool $optimistic);

	/**
	 * @template T
	 * @param Closure():T $operator
	 * @return Generator<void,void,mixed,bool>|bool
	 */
	public function update(string $key, Closure $operator, bool $optimistic);

	/**
	 * @return Generator<void,void,mixed,bool>|bool
	 */
	public function unset(string $key, bool $optimistic);

	/**
	 * @return Generator<void,void,mixed,void>|void
	 */
	public function sync();
}