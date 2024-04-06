<?php

namespace blackjack200\economy\provider\await\column\impl;

use blackjack200\economy\provider\await\column\Column;
use blackjack200\economy\provider\await\column\DataStatus;
use blackjack200\economy\provider\await\column\WeakLRUCache;
use blackjack200\economy\provider\next\AccountDataProxy;
use blackjack200\economy\provider\next\impl\types\IdentifierProvider;
use libasync\await\Await;
use prokits\player\PracticePlayer;

/**
 * @template T of scalar
 */
class MysqlColumn implements Column {
	/** @var WeakLRUCache<PracticePlayer,string,T|DataStatus> */
	protected WeakLRUCache $cache;

	public function __construct(
		protected readonly string   $key,
		/** @var T $default */
		protected readonly mixed    $default,
		/** @var \Closure(mixed|null):T $hydrator */
		protected readonly \Closure $hydrator,
	) {
		$this->cache = new WeakLRUCache(50, 100);
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
			/** @noinspection PhpBooleanCanBeSimplifiedInspection */
			false && yield;
			return $cached;
		}
		return yield from $this->get($player);
	}

	public function getCachedKeepLatest(PracticePlayer|string $player) {
		$data = yield from $this->getCached($player);
		yield from $this->syncCache($player);
		return $data;
	}

	public function refresh(PracticePlayer|string $player) : \Generator {
		yield from $this->get($player);
	}

	public function reset(PracticePlayer|string $player) : \Generator {
		yield from $this->set($player, $this->default);
	}

	public function set(PracticePlayer|string $player, $data) : \Generator|bool {
		$data = ($this->hydrator)($data);
		$success = yield from AccountDataProxy::set(IdentifierProvider::autoOrName($player), $this->key, $data);
		if ($success) {
			yield from $this->waitCacheReady($player);
			$this->cache->put($player, $data);
		}
		return $success;
	}

	public function delete(PracticePlayer|string $player) : \Generator|bool {
		$success = yield from AccountDataProxy::delete(IdentifierProvider::autoOrName($player), $this->key);
		if ($success) {
			yield from $this->waitCacheReady($player);
			$this->cache->clear($player);
		}
		return $success;
	}

	protected function waitCacheReady(PracticePlayer|string $player) : \Generator {
		$cached = $this->cache->get($player);
		while ($cached === DataStatus::ACQUIRING) {
			yield from Await::udelay(50);
			$cached = $this->cache->get($player);
		}
		return $cached;
	}

	protected function syncCache(PracticePlayer|string $player) {
		$this->waitCacheReady($player);
		$this->cache->put($player, DataStatus::ACQUIRING);
		$all = yield from AccountDataProxy::getAll(IdentifierProvider::autoOrName($player));
		/** @var T $fetchedRawData */
		$fetchedRawData = $all[$this->key] ?? $this->default;
		$data = ($this->hydrator)($fetchedRawData);
		$this->cache->put($player, $data);
		return $data;
	}
}