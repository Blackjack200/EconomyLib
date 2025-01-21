<?php

namespace blackjack200\economy\provider\await\column;

use blackjack200\economy\provider\next\impl\types\Identity;
use prokits\player\PracticePlayer;

/**
 * @template T
 */
interface Column extends ReadonlyCachedData {
	public function getKey() : string;


	public function reset(PracticePlayer|Identity|string $player) : \Generator;

	/**
	 * @param T $data
	 */
	public function set(PracticePlayer|Identity|string $player, $data) : \Generator|bool;

	public function delete(PracticePlayer|Identity|string $player) : \Generator|bool;
}