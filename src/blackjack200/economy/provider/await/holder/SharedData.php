<?php

namespace blackjack200\economy\provider\await\holder;

use blackjack200\economy\provider\next\AccountDataProxy;
use blackjack200\economy\provider\next\impl\types\IdentifierProvider;
use Closure;
use libasync\await\lock\rw\MutexRefCell;
use prokits\player\PracticePlayer;
use WeakMap;

class SharedData implements SharedDataHolder {
	/** @var MutexRefCell<array|null> */
	private MutexRefCell $value;
	/** @var WeakMap<IdentifierProvider,self> */
	private static WeakMap $cache;


	public function __construct(
		private readonly IdentifierProvider $id
	) {
		$this->value = new MutexRefCell(null);
	}

	public static function from(IdentifierProvider $id) : self {
		if (!isset(self::$cache)) {
			self::$cache = new WeakMap();
		}
		if (!isset(self::$cache[$id])) {
			self::$cache[$id] = new self($id);
		}
		return self::$cache[$id];
	}

	public static function autoOrName(PracticePlayer|string $id) : self {
		return self::from(IdentifierProvider::autoOrName($id));
	}

	public function get(string $key, bool $preferCache, \Closure $validator) {
		if ($preferCache) {
			$last = $this->value->getLastWrite();
			yield;
			if ($last !== null) {
				return $validator($last[$key] ?? null);
			}
		}
		$val = null;
		yield from $this->value->trySet(function($set) use ($key, &$val) {
			$data = yield from AccountDataProxy::getAll($this->id);
			if (!isset($data[$key])) {
				return null;
			}
			$set($data);
			$val = $data[$key];
		});
		return $validator($val);
	}

	public function set(string $key, $value, bool $optimistic) {
		$updateLocal = fn() => $this->value->trySet(function($set, $get) use ($value, $key) {
			$v = $get();
			if ($v !== null && isset($v[$key])) {
				$v[$key] = $value;
				$set($v);
			}
		});
		if ($optimistic) {
			yield from $updateLocal();
		}
		$success = yield from AccountDataProxy::set($this->id, $key, $value);
		if ($success) {
			yield from $updateLocal();
		} else {
			yield from $this->sync();
		}
		return $success;
	}

	public function unset(string $key, bool $optimistic) {
		$updateLocal = fn() => $this->value->trySet(function($set, $get) use ($key) {
			$v = $get();
			if ($v !== null) {
				unset($v[$key]);
				$set($v);
			}
		});
		if ($optimistic) {
			yield from $updateLocal();
		}
		$success = yield from AccountDataProxy::delete($this->id, $key);
		if ($success) {
			yield from $updateLocal();
		} else {
			yield from $this->sync();
		}
		return $success;
	}

	public function sync() {
		yield from $this->value->trySet(function($set) {
			$data = yield from AccountDataProxy::getAll($this->id);
			$set($data ?? []);
		});
	}

	public function readCache(string $key, \Closure $validator) {
		$last = $this->value->getLastWrite();
		if ($last !== null) {
			return $validator($last[$key] ?? null);
		}
		return $validator(null);
	}

	public function update(string $key, Closure $operator, bool $optimistic) {
		$updateLocal = fn() => $this->value->trySet(function($set, $get) use ($operator, $key) {
			$v = $get();
			if ($v !== null && isset($v[$key])) {
				$v[$key] = $operator($v[$key]);
				$set($v);
			}
		});
		if ($optimistic) {
			yield from $updateLocal();
		}
		$success = yield from AccountDataProxy::update($this->id, $key, $operator);
		if ($success) {
			yield from $updateLocal();
		} else {
			yield from $this->sync();
		}
		return $success;
	}


	public function numericUpdate(string $key, int $delta, bool $signed, bool $optimistic) {
		$updateLocal = fn() => $this->value->trySet(function($set, $get) use ($delta, $signed, $key) {
			$v = $get();
			if ($v !== null && isset($v[$key])) {
				$v[$key] = max($signed ? PHP_INT_MIN : 0, $v[$key] + $delta);
				$set($v);
			}
		});

		if ($optimistic) {
			yield from $updateLocal();
		}

		$success = yield from AccountDataProxy::numericDelta($this->id, $key, $delta, $signed);

		yield from $updateLocal();

		return $success;
	}
}