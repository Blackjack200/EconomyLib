<?php

namespace blackjack200\economy\provider\await\holder;

use Closure;
use Generator;

interface SharedDataHolder {
	/**
	 * @template T
	 * @param Closure(scalar|null):T $validator
	 * @return Generator<void,void,void,T|null>|T|null
	 */
	public function get(string $key, bool $preferCache, \Closure $validator);

	/**
	 * @template T
	 * @param Closure(scalar|null):T $validator
	 * @return Generator<void,void,void,T|null>|T|null
	 */
	public function readCache(string $key, \Closure $validator);

	/**
	 * @template T
	 * @param T $value
	 * @return Generator<void,void,void,bool>|bool
	 */
	public function set(string $key, $value, bool $optimistic);

	/**
	 * @template T
	 * @param Closure():T $operator
	 * @return Generator<void,void,void,bool>|bool
	 */
	public function update(string $key, Closure $operator, bool $optimistic);

	/**
	 * @return Generator<void,void,void,bool>|bool
	 */
	public function unset(string $key, bool $optimistic);

	/**
	 * @return Generator<void,void,void,void>|void
	 */
	public function sync();
}