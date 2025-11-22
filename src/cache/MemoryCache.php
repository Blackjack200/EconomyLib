<?php

namespace blackjack200\cache;

class MemoryCache implements CacheInterface {
	private array $mem = [];

	public static function new() : self {
		return new self();
	}

	public function put(string $key, mixed $val) : bool {
		$this->mem[$key] = $val;
		return true;
	}

	public function get(string $key) : mixed {
		return $this->mem[$key] ?? null;
	}

	public function has(string $key) : bool {
		return array_key_exists($key, $this->mem);
	}

	public function clear(string $key) : bool {
		unset($this->mem[$key]);
		return true;
	}

	public function keys() : array {
		return array_keys($this->mem);
	}

	public function clearAll() : bool {
		$this->mem = [];
		return true;
	}
}