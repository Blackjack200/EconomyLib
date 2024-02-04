<?php

namespace blackjack200\economy\provider\await;

use prokits\player\PracticePlayer;
use WeakMap;

/**
 * @template T of scalar
 */
class RowData extends BaseRowData {
	private WeakMap $map;

	protected function setup() : void {
		$this->map = new WeakMap();
	}

	protected function readCache(PracticePlayer|string $player) : mixed {
		return $this->map[$player] ?? null;
	}

	public function writeCache(mixed $data, PracticePlayer|string $player) : void {
		$this->map[$player] = $data;
	}

	public function clearCache() : void {
		$this->map = new WeakMap();
	}
}