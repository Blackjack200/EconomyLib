<?php

namespace blackjack200\economy\provider\await\holder;

use blackjack200\cache\CacheInterface;
use blackjack200\cache\LRUCache;
use blackjack200\economy\provider\next\AccountDataProxy;
use blackjack200\economy\provider\next\impl\tools\BidirectionalIndexedDataVisitor;
use blackjack200\economy\provider\next\impl\types\IdentifierProvider;
use blackjack200\economy\provider\next\impl\types\Identity;
use blackjack200\economy\provider\UpdateResult;
use libasync\await\Await;
use libasync\await\lock\rw\MutexRefCell;
use pocketmine\utils\Utils;
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

	public function sync(bool $force = false) {
		if (!$force && (microtime(true) - $this->lastSync) < 5) {
			yield Await::suspend;
			return;
		}
		$this->lastSync = microtime(true);
		yield from $this->mappedData->set(function($set, $get) {
			$data = yield from AccountDataProxy::getAll(IdentifierProvider::autoOrName($this->owner));
			$rawData = $data ?? [];
			$oldMappedData = $get();
			$newMappedData = [];
			foreach (Utils::stringifyKeys(self::$registered) as $key => $row) {
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
				$oldValue = $v[$key] ?? null;
				$v[$key] = $value;

				$row = self::$registered[$key] ?? null;
				if ($row !== null && $value !== $oldValue && $row->onUpdate !== null) {
					($row->onUpdate)($this->owner, $oldValue, $value);
				}

				$set($v);
				return UpdateResult::SUCCESS;
			});
		}
		$encoded = isset(self::$registered[$key]) ? (self::$registered[$key]->behaviour->encoder)($value) : $value;
		$result = yield from AccountDataProxy::set(IdentifierProvider::autoOrName($this->owner), $key, $encoded);
		if ($result === UpdateResult::SUCCESS) {
			yield from $this->mappedData->set(function($set, $get) use ($value, $key) {
				$oldMappedData = $get();
				$row = self::$registered[$key] ?? null;
				if ($row !== null) {
					$oldValue = $oldMappedData[$key] ?? null;
					if ($value !== $oldValue && $row->onUpdate !== null) {
						($row->onUpdate)($this->owner, $oldValue, $value);
					}
				}
				$oldMappedData[$key] = $value;
				$set($oldMappedData);
			});
		} else if ($result === UpdateResult::INTERNAL_ERROR) {
			yield from $this->sync();
		}
		return $result;
	}

	public function unset(string $key, bool $optimistic) {
		$commit = fn() => $this->mappedData->set(function($set, $get) use ($key) {
			$v = $get();
			$oldValue = $v[$key] ?? null;
			unset($v[$key]);

			$row = self::$registered[$key] ?? null;
			if ($row !== null && $row->onUpdate !== null) {
				($row->onUpdate)($this->owner, $oldValue, null);
			}

			$set($v);
			return UpdateResult::SUCCESS;
		});
		if ($optimistic) {
			yield from $commit();
		}
		$result = yield from AccountDataProxy::delete(IdentifierProvider::autoOrName($this->owner), $key);
		if ($result === UpdateResult::SUCCESS) {
			yield from $commit();
		} else if ($result === UpdateResult::INTERNAL_ERROR) {
			yield from $this->sync();
		}
		return $result;
	}

	public function update(string $key, \Closure $operator, bool $optimistic) {
		$updateLocal = fn() => $this->mappedData->trySet(function($set, $get) use ($operator, $key) {
			$v = $get();
			if ($v !== null && isset($v[$key])) {
				$oldValue = $v[$key];
				$v[$key] = $newValue = $operator($v[$key]);

				$row = self::$registered[$key] ?? null;
				if ($row !== null && $newValue !== $oldValue && $row->onUpdate !== null) {
					($row->onUpdate)($this->owner, $oldValue, $newValue);
				}

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
		$result = yield from AccountDataProxy::update(IdentifierProvider::autoOrName($this->owner), $key, $proxiedOperator);
		if ($result === UpdateResult::SUCCESS) {
			yield from $updateLocal();
		} else if ($result === UpdateResult::INTERNAL_ERROR) {
			yield from $this->sync();
		}
		return $result;
	}


	public function numericUpdate(string $key, int $delta, bool $signed, bool $optimistic) {
		$updateLocal = fn() => $this->mappedData->trySet(function($set, $get) use ($delta, $signed, $key) {
			$v = $get();
			if ($v !== null && isset($v[$key])) {
				$oldValue = $v[$key];
				$v[$key] = $newValue = max($signed ? PHP_INT_MIN : 0, $v[$key] + $delta);
				$set($v);

				$row = self::$registered[$key] ?? null;
				if ($row !== null && $newValue !== $oldValue && $row->onUpdate !== null) {
					($row->onUpdate)($this->owner, $oldValue, $newValue);
				}
			}
		});

		if ($optimistic) {
			yield from $updateLocal();
		}

		$result = yield from AccountDataProxy::numericDelta(IdentifierProvider::autoOrName($this->owner), $key, $delta, $signed);

		if ($result === UpdateResult::SUCCESS) {
			yield from $updateLocal();
		} else if ($result === UpdateResult::INTERNAL_ERROR) {
			yield from $this->sync();
		}

		return $result;
	}

	public static function dsort(string $key, int $limit) : \Generator|BidirectionalIndexedDataVisitor {
		return yield from AccountDataProxy::sort($key, $limit, false);
	}

	public static function asort(string $key, int $limit) : \Generator|BidirectionalIndexedDataVisitor {
		return yield from AccountDataProxy::sort($key, $limit, true);
	}
}