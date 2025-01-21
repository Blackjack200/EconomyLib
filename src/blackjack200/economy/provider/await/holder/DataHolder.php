<?php

namespace blackjack200\economy\provider\await\holder;

use blackjack200\cache\CacheInterface;
use blackjack200\cache\LRUCache;
use blackjack200\economy\provider\next\AccountDataProxy;
use blackjack200\economy\provider\next\impl\tools\BidirectionalIndexedDataVisitor;
use blackjack200\economy\provider\next\impl\types\IdentifierProvider;
use blackjack200\economy\provider\next\impl\types\Identity;
use libasync\await\Await;
use libasync\await\lock\rw\MutexRefCell;
use prokits\player\PracticePlayer;

class DataHolder implements SharedDataHolder {
	/** @var array<string,RegisteredRow> */
	private static array $registered = [];
	/** @var CacheInterface<self> */
	private static CacheInterface $cache;
	/** @var MutexRefCell<array> */
	private MutexRefCell $mappedData;
	private float $lastSync = 0;

	public function __construct(
		private readonly PracticePlayer|Identity $owner
	) {
		$this->mappedData = new MutexRefCell([]);
	}

	/**
	 * Register a new row for this DataHolder.
	 *
	 * @param string $key
	 * @param Behaviour $behaviour
	 * @param mixed $defaultValue
	 */
	public static function register(
		string    $key,
		Behaviour $behaviour,
		mixed     $defaultValue,
	) : RegisteredRow {
		return self::$registered[$key] = new RegisteredRow($behaviour, $defaultValue);
	}

	public static function of(PracticePlayer|Identity|string $owner) : self {
		if (is_string($owner)) {
			$owner = new Identity($owner, null, false);
		}
		if (!isset(self::$cache)) {
			self::$cache = new LRUCache(255);
		}
		$hash = $owner->asIdentity()->hash();
		if (!self::$cache->has($hash)) {
			if($owner instanceof Identity) {
				$owner = clone $owner;
			}
			self::$cache->put($hash, new self($owner));
		}
		return self::$cache->get($hash);
	}

	public function get(string $key, bool $preferCache) {
		if ($preferCache && array_key_exists($key, $this->mappedData->getLastWrite() ?? [])) {
			yield Await::suspend;
			return $this->mappedData->getLastWrite()[$key];
		}
		yield from $this->sync();
		return yield from $this->mappedData->get(static fn($value) => ($value[$key] ?? null));
	}

	public function readCached(string $key) {
		if (array_key_exists($key, $this->mappedData->getLastWrite() ?? [])) {
			return $this->mappedData->getLastWrite()[$key];
		}
		if (isset(self::$registered[$key])) {
			return self::$registered[$key]->defaultValue;
		}
		throw new \InvalidArgumentException("key '$key' is not registered");
	}

	public function sync() {
		if ((microtime(true) - $this->lastSync) < 10) {
			yield Await::suspend;
			return;
		}
		$this->lastSync = microtime(true);
		yield from $this->mappedData->set(function($set, $get) {
			$data = yield from AccountDataProxy::getAll(IdentifierProvider::autoOrName($this->owner));
			$rawData = $data ?? [];
			$oldMappedData = $get();
			$newMappedData = [];
			foreach (self::$registered as $key => $row) {
				$oldValue = $oldMappedData[$key] ?? null;
				$newValue = ($row->behaviour->decoder)($rawData[$key] ?? null);
				if ($newValue !== $oldValue && $row->onUpdate !== null) {
					($row->onUpdate)($this->owner, $oldValue, $newValue);
				}
				$newMappedData[$key] = $newValue;
			}
			$set($newMappedData);
		});
	}

	public function set(string $key, $value, bool $optimistic) {
		if ($optimistic) {
			yield from $this->mappedData->set(function($set, $get) use ($value, $key) {
				$v = $get();
				$v[$key] = $value;
				$set($v);
				return true;
			});
		}
		$encoded = isset(self::$registered[$key]) ? (self::$registered[$key]->behaviour->encoder)($value) : $value;
		$success = yield from AccountDataProxy::set(IdentifierProvider::autoOrName($this->owner), $key, $encoded);
		if ($success) {
			yield from $this->mappedData->set(function($set, $get) use ($value, $key) {
				$v = $get();
				$v[$key] = $value;
				$set($v);
			});
		} else {
			yield from $this->sync();
		}
		return $success;
	}

	public function unset(string $key, bool $optimistic) {
		if ($optimistic) {
			yield from $this->mappedData->set(function($set, $get) use ($key) {
				$v = $get();
				unset($v[$key]);
				$set($v);
				return true;
			});
		}
		$success = yield from AccountDataProxy::delete(IdentifierProvider::autoOrName($this->owner), $key);
		if ($success) {
			yield from $this->mappedData->set(function($set, $get) use ($key) {
				$v = $get();
				unset($v[$key]);
				$set($v);
			});
		} else {
			yield from $this->sync();
		}
		return $success;
	}

	public function update(string $key, \Closure $operator, bool $optimistic) {
		$updateLocal = fn() => $this->mappedData->trySet(function($set, $get) use ($operator, $key) {
			$v = $get();
			if ($v !== null && isset($v[$key])) {
				$v[$key] = $operator($v[$key]);
				$set($v);
			}
		});
		if ($optimistic) {
			yield from $updateLocal();
		}
		$proxiedOperator = static function($old) use ($operator, $key) {
			$behavior = isset(self::$registered[$key]) ? self::$registered[$key]->behaviour : null;
			$newValue = $operator($behavior !== null ? ($behavior->decoder)($old) : null);
			return $behavior !== null ? ($behavior->encoder)($newValue) : $newValue;
		};
		$success = yield from AccountDataProxy::update(IdentifierProvider::autoOrName($this->owner), $key, $proxiedOperator);
		if ($success) {
			yield from $updateLocal();
		} else {
			yield from $this->sync();
		}
		return $success;
	}


	public function numericUpdate(string $key, int $delta, bool $signed, bool $optimistic) {
		$updateLocal = fn() => $this->mappedData->trySet(function($set, $get) use ($delta, $signed, $key) {
			$v = $get();
			if ($v !== null && isset($v[$key])) {
				$v[$key] = max($signed ? PHP_INT_MIN : 0, $v[$key] + $delta);
				$set($v);
			}
		});

		if ($optimistic) {
			yield from $updateLocal();
		}

		$success = yield from AccountDataProxy::numericDelta(IdentifierProvider::autoOrName($this->owner), $key, $delta, $signed);

		yield from $updateLocal();

		return $success;
	}

	public static function dsort(string $key, int $limit) : \Generator|BidirectionalIndexedDataVisitor {
		return yield from AccountDataProxy::sort($key, $limit, false);
	}

	public static function asort(string $key, int $limit) : \Generator|BidirectionalIndexedDataVisitor {
		return yield from AccountDataProxy::sort($key, $limit, true);
	}
}