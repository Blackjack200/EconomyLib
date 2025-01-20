<?php

namespace blackjack200\economy\provider\await\column\impl;

use blackjack200\cache\CacheInterface;
use blackjack200\cache\LRUCache;
use blackjack200\economy\provider\await\column\DataLock;
use blackjack200\economy\provider\await\holder\Behaviour;
use blackjack200\economy\provider\await\holder\DataHolder;
use blackjack200\economy\provider\next\AccountDataProxy;
use blackjack200\economy\provider\next\impl\types\IdentifierProvider;
use blackjack200\economy\provider\next\impl\types\Identity;
use Generator;
use libasync\await\Await;
use prokits\player\PracticePlayer;
use function libasync\async;

/**
 * @template T of scalar
 */
class LocallyCachedMysqlColumn extends MysqlColumn {
	/** @var CacheInterface<T|DataLock> */
	protected CacheInterface $cache;

	public function __construct(string $key, mixed $default, Behaviour $behaviour) {
		parent::__construct($key, $default, $behaviour);
		$this->cache = new LRUCache(1024);
	}

	public function getKey() : string {
		return $this->key;
	}

	public function get(PracticePlayer|Identity $player) {
		return yield from $this->syncCache($player);
	}

	public function getCached(PracticePlayer|Identity $player) {
		$cached = yield from $this->waitCacheReady($player->asIdentity()->hash());

		if ($cached !== null) {
			yield Await::suspend;
			return $cached;
		}
		return yield from $this->syncCache($player);
	}

	public function getCachedKeepLatest(PracticePlayer|Identity $player) {
		$data = yield from $this->getCached($player);
		Await::do($this->syncCache($player))->logError();
		return $data;
	}

	public function refresh(PracticePlayer|Identity $player) : \Generator {
		yield from $this->syncCache($player);
	}

	public function reset(PracticePlayer|Identity $player) : \Generator {
		yield from $this->set($player, $this->default);
	}

	public function set(PracticePlayer|Identity $player, $value) : Generator|bool {
		$this->cache->put($player->asIdentity()->hash(), $value);
		$data = DataHolder::of($player);
		return yield from $data->set($this->key, $value, false);
	}

	public function delete(PracticePlayer|Identity $player) : \Generator|bool {
		$success = yield from AccountDataProxy::delete(IdentifierProvider::autoOrName($player), $this->key);
		if ($success) {
			Await::do($this->syncCache($player))->panic();
		}
		return $success;
	}

	protected function waitCacheReady(string $hash) : \Generator {
		$cached = $this->cache->get($hash);
		while ($cached instanceof DataLock) {
			yield from Await::udelay(50);
			$cached = $this->cache->get($hash);
		}
		return $cached;
	}

	protected function syncCache(PracticePlayer|Identity $player, bool $preferCache = false) {
		$hash = $player->asIdentity()->hash();
		$this->waitCacheReady($hash);
		$lock = new DataLock();
		$this->cache->put($hash, $lock);
		$data = yield from ($preferCache ? parent::getCached($player) : $this->getLatest($player));
		$this->cache->put($hash, $data);
		return $data;
	}

	public function getCache() : CacheInterface { return $this->cache; }

	public function readCached(PracticePlayer|Identity $player) {
		$hash = $player->asIdentity()->hash();
		$cached = $this->cache->get($hash);
		if ($cached instanceof DataLock) {
			return $this->default;
		}
		if (!$this->cache->has($hash)) {
			async($this->syncCache($player, true))->logError();
		}
		return $cached ?? $this->default;
	}
}