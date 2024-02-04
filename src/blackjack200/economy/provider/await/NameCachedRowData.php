<?php

namespace blackjack200\economy\provider\await;

use prokits\player\PracticePlayer;

/**
 * @template T of scalar
 */
class NameCachedRowData extends BaseRowData {
	private array $map = [];
	private int $maxCount = 1024;

	protected function readCache(PracticePlayer|string $player) : mixed {
		return $this->map[is_string($player) ? $player : $player->getName()] ?? null;
	}

	public function writeCache(mixed $data, PracticePlayer|string $player) : void {
		$this->map[is_string($player) ? $player : $player->getName()] = $data;
		if (count($this->map) > $this->maxCount) {
			$this->clearCache();
		}
	}

	public function clearCache() : void {
		$this->map = [];
	}
}