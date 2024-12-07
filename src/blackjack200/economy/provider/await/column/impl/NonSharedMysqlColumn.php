<?php

namespace blackjack200\economy\provider\await\column\impl;

use blackjack200\economy\provider\await\column\DataLock;
use blackjack200\economy\provider\await\column\WeakOrStrongCache;
use blackjack200\economy\provider\next\AccountDataProxy;
use blackjack200\economy\provider\next\impl\types\IdentifierProvider;
use libasync\await\Await;
use prokits\player\PracticePlayer;
use function libasync\async;

/**
 * @template T of scalar
 */
class NonSharedMysqlColumn extends MysqlColumn {
	/** @var WeakOrStrongCache<PracticePlayer,string,T|DataLock> */
	protected WeakOrStrongCache $cache;

	public function __construct(string $key, mixed $default, \Closure $hydrator) {
		parent::__construct($key, $default, $hydrator);
		$this->cache = new WeakOrStrongCache(PHP_INT_MAX, 3000);
	}

	public function getKey() : string {
		return $this->key;
	}

	public function get(PracticePlayer|string $player) {
		return yield from $this->syncCache($player);
	}

	public function getCached(PracticePlayer|string $player) {
		$cached = yield from $this->waitCacheReady($player);

		if ($cached !== null) {
			yield;
			return $cached;
		}
		return yield from $this->syncCache($player);
	}

	public function getCachedKeepLatest(PracticePlayer|string $player) {
		$data = yield from $this->getCached($player);
		Await::do($this->syncCache($player))->logError();
		return $data;
	}

	public function refresh(PracticePlayer|string $player) : \Generator {
		yield from $this->syncCache($player);
	}

	public function reset(PracticePlayer|string $player) : \Generator {
		yield from $this->set($player, $this->default);
	}

	public function set(PracticePlayer|string $player, $data) : \Generator|bool {
		$this->cache->put($player, $data);
		$success = yield from AccountDataProxy::set(IdentifierProvider::autoOrName($player), $this->key, $data);

		if ($success) {
			Await::do($this->syncCache($player))->logError();
		} else {
			yield from $this->syncCache($player);
		}
		return $success;
	}

	public function delete(PracticePlayer|string $player) : \Generator|bool {
		$success = yield from AccountDataProxy::delete(IdentifierProvider::autoOrName($player), $this->key);
		if ($success) {
			Await::do($this->syncCache($player))->panic();
		}
		return $success;
	}

	protected function waitCacheReady(PracticePlayer|string $player) : \Generator {
		$cached = $this->cache->get($player);
		while ($cached instanceof DataLock) {
			yield from Await::udelay(50);
			$cached = $this->cache->get($player);
		}
		return $cached;
	}

	protected function syncCache(PracticePlayer|string $player) {
		$this->waitCacheReady($player);
		$lock = new DataLock();
		$this->cache->put($player, $lock);
		$data = yield from parent::get($player);
		$this->cache->put($player, $data);
		return $data;
	}

	public function getCache() : WeakOrStrongCache { return $this->cache; }

	public function readCached(PracticePlayer|string $player) {
		$cached = $this->cache->get($player);
		if ($cached instanceof DataLock) {
			return $this->default;
		}
		if ($cached === null) {
			async($this->syncCache($player))->logError();
		}
		return $cached ?? $this->default;
	}
}