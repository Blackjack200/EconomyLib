<?php

namespace blackjack200\economy\provider\await\column\impl;

use blackjack200\economy\provider\await\column\Column;
use blackjack200\economy\provider\await\holder\SharedPlayerDataHolder;
use Generator;
use libasync\await\Await;
use prokits\player\PracticePlayer;

/**
 * @template T of scalar
 */
class MysqlColumn implements Column {
	public function __construct(
		protected readonly string   $key,
		/** @var T $default */
		protected readonly mixed    $default,
		/** @var \Closure(mixed|null):T $hydrator */
		protected readonly \Closure $hydrator,
	) {
	}


	public function getKey() : string {
		return $this->key;
	}

	public function reset(PracticePlayer|string $player) : Generator {
		$data = SharedPlayerDataHolder::autoOrName($player);
		return yield from $data->set($this->key, $this->default, false);
	}

	public function set(PracticePlayer|string $player, $value) : Generator|bool {
		$data = SharedPlayerDataHolder::autoOrName($player);
		return yield from $data->set($this->key, $value, false);
	}

	public function delete(PracticePlayer|string $player) : Generator|bool {
		$data = SharedPlayerDataHolder::autoOrName($player);
		return yield from $data->unset($this->key, false);
	}

	public function getLatest(PracticePlayer|string $player) {
		$data = SharedPlayerDataHolder::autoOrName($player);
		return yield from $data->get($this->key, false, $this->hydrator);
	}

	/**
	 * @deprecated
	 * @see MysqlColumn::getLatest())
	 */
	public function get(PracticePlayer|string $player) {
		return yield from $this->getLatest($player);
	}

	public function getCached(PracticePlayer|string $player) {
		$data = SharedPlayerDataHolder::autoOrName($player);
		return yield from $data->get($this->key, true, $this->hydrator);
	}

	public function readCached(PracticePlayer|string $player) {
		return SharedPlayerDataHolder::autoOrName($player)->readCache($this->key, $this->hydrator);
	}

	public function getCachedKeepLatest(PracticePlayer|string $player) {
		$data = SharedPlayerDataHolder::autoOrName($player);
		Await::do(Await::f2c(static fn() => $data->sync()))->logError();
		yield;
		return $data->readCache($this->key, $this->hydrator);
	}

	public function refresh(PracticePlayer|string $player) : Generator {
		$data = SharedPlayerDataHolder::autoOrName($player);
		yield from $data->sync();
	}
}