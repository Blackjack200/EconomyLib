<?php

namespace blackjack200\economy\provider\await\holder;

use blackjack200\economy\provider\next\AccountDataProxy;
use blackjack200\economy\provider\next\impl\types\IdentifierProvider;
use Closure;
use libasync\await\lock\rw\LockedValue;
use prokits\player\PracticePlayer;
use WeakMap;

class SharedData implements SharedDataHolder {
	/** @var LockedValue<array|null> */
	private LockedValue $value;
	/** @var WeakMap<IdentifierProvider,self> */
	private static WeakMap $cache;


	public function __construct(
		private readonly IdentifierProvider $id
	) {
		$this->value = new LockedValue(null);
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

	public function get(string $key, bool $useCache, \Closure $validator) {
		if ($useCache) {
			$last = $this->value->getLastWrite();
			if ($last !== null) {
				return $validator($last[$key] ?? null);
			}
			return $validator(null);
		}
		yield from $this->value->trySet(function($set) use ($key) {
			$data = yield from AccountDataProxy::getAll($this->id);
			if (!isset($data[$key])) {
				return null;
			}
			$set($data);
			return $data[$key];
		}, $r);
		return $validator($r);
	}

	public function set(string $key, $value, bool $optimistic) {
		if ($optimistic) {
			yield from $this->value->trySet(function($set, $get) use ($value, $key) {
				$v = $get();
				if ($v !== null && isset($v[$key])) {
					$v[$key] = $value;
					$set($v);
				}
			}, $result);
		}
		$success = yield from AccountDataProxy::set($this->id, $key, $value);
		yield from $this->sync();
		return $success;
	}

	public function unset(string $key, bool $optimistic) {
		if ($optimistic) {
			yield from $this->value->trySet(function($set, $get) use ($key) {
				$v = $get();
				if ($v !== null) {
					unset($v[$key]);
					$set($v);
				}
			}, $result);
		}
		$success = yield from AccountDataProxy::delete($this->id, $key);
		yield from $this->sync();
		return $success;
	}

	public function sync() {
		yield from $this->value->trySet(function($set) {
			$data = yield from AccountDataProxy::getAll($this->id);
			if ($data === null) {
				return;
			}
			$set($data);
		}, $r);
	}

	public function readCache(string $key, \Closure $validator) {
		$last = $this->value->getLastWrite();
		if ($last !== null) {
			return $validator($last[$key] ?? null);
		}
		return $validator(null);
	}

	public function update(string $key, Closure $operator, bool $optimistic) {
		if ($optimistic) {
			yield from $this->value->trySet(function($set, $get) use ($operator, $key) {
				$v = $get();
				if ($v !== null && isset($v[$key])) {
					$v[$key] = $operator($v[$key]);
					$set($v);
				}
			}, $result);
		}
		$success = yield from AccountDataProxy::update($this->id, $key, $operator);
		yield from $this->sync();
		return $success;
	}
}