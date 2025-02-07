<?php

namespace blackjack200\economy\provider\await\column;

use blackjack200\cache\CacheInterface;
use blackjack200\cache\LRUCache;
use WeakMap;

/**
 * @template WeakK of object
 * @template StrongK of scalar
 * @template V
 */
class WeakOrStrongCache {
	/** @var WeakMap<WeakK,V> */
	protected WeakMap $weak;
	/** @var CacheInterface<V> */
	protected CacheInterface $strong;

	public function __construct(
		private readonly int $weakCapacity,
		private readonly int $lruCapacity,
	) {
		$this->weak = new WeakMap();
		$this->strong = new LRUCache($this->lruCapacity);
	}

	/**
	 * @param WeakK|StrongK $key
	 * @param V $val
	 */
	public function put($key, mixed $val) : bool {
		if (is_object($key)) {
			if (count($this->weak) > $this->weakCapacity) {
				$this->weak = new WeakMap();
			}
			$this->weak[$key] = $val;
			return true;
		}
		return $this->strong->put($key, $val);
	}

	/**
	 * @param WeakK|StrongK $key
	 * @return V|null
	 */
	public function get($key) : mixed {
		if (is_object($key)) {
			return $this->weak[$key] ?? null;
		}
		return $this->strong->get($key);
	}

	/**
	 * @param WeakK|StrongK $key
	 */
	public function has($key) : bool {
		if (is_object($key)) {
			return isset($this->weak[$key]);
		}
		return $this->strong->has($key);
	}

	/**
	 * @param WeakK|StrongK $key
	 */
	public function clear($key) : bool {
		if (is_object($key)) {
			unset($this->weak[$key]);
			return true;
		}
		return $this->strong->clear($key);
	}

	public function clearAll() : bool {
		$this->weak = new WeakMap();
		return $this->strong->clearAll();
	}
}