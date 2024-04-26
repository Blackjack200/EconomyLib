<?php

namespace blackjack200\economy\provider\await\column;

use prokits\player\PracticePlayer;

/**
 * @template T
 */
interface Column extends ReadonlyCachedData {
	public function getKey() : string;


	public function reset(PracticePlayer|string $player) : \Generator;

	/**
	 * @param T $data
	 */
	public function set(PracticePlayer|string $player, $data) : \Generator|bool;

	public function delete(PracticePlayer|string $player) : \Generator|bool;
}