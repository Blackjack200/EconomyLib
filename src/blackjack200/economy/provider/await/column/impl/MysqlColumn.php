<?php

namespace blackjack200\economy\provider\await\column\impl;

use blackjack200\economy\provider\await\column\Column;
use blackjack200\economy\provider\await\holder\Behaviour;
use blackjack200\economy\provider\await\holder\DataHolder;
use blackjack200\economy\provider\next\impl\types\Identity;
use Generator;
use libasync\await\Await;
use prokits\player\PracticePlayer;

/**
 * @template T of scalar
 */
class MysqlColumn implements Column {
	public function __construct(
		protected readonly string    $key,
		/** @var T $default */
		protected readonly mixed     $default,
		protected readonly Behaviour $behaviour,
	) {
		DataHolder::register($this->key, $this->behaviour, $default);
	}


	public function getKey() : string {
		return $this->key;
	}

	public function reset(PracticePlayer|Identity $player) : Generator {
		$data = DataHolder::of($player);
		return yield from $data->set($this->key, $this->default, false);
	}

	public function set(PracticePlayer|Identity $player, $value) : Generator|bool {
		$data = DataHolder::of($player);
		return yield from $data->set($this->key, $value, false);
	}

	public function delete(PracticePlayer|Identity $player) : Generator|bool {
		$data = DataHolder::of($player);
		return yield from $data->unset($this->key, false);
	}

	public function getLatest(PracticePlayer|Identity $player) {
		$data = DataHolder::of($player);
		return yield from $data->get($this->key, false);
	}

	/**
	 * @deprecated
	 * @see MysqlColumn::getLatest())
	 */
	public function get(PracticePlayer|Identity $player) {
		return yield from $this->getLatest($player);
	}

	public function getCached(PracticePlayer|Identity $player) {
		$data = DataHolder::of($player);
		return yield from $data->get($this->key, true);
	}

	public function readCached(PracticePlayer|Identity $player) {
		return DataHolder::of($player)->readCached($this->key);
	}

	public function getCachedKeepLatest(PracticePlayer|Identity $player) {
		$data = DataHolder::of($player);
		Await::do(Await::f2c(static fn() => $data->sync()))->logError();
		yield Await::suspend;
		return $data->readCached($this->key);
	}

	public function refresh(PracticePlayer|Identity $player) : Generator {
		$data = DataHolder::of($player);
		yield from $data->sync();
	}
}