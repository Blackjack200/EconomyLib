<?php

namespace blackjack200\economy\provider\await\column;

use prokits\player\PracticePlayer;

/**
 * @template T
 */
interface Column {

	public function getKey() : string;

	/**
	 * @return T
	 */
	public function get(PracticePlayer|string $player);

	/**
	 * @return T
	 */
	public function getCached(PracticePlayer|string $player);

	/**
	 * @return T|\Generator
	 */
	public function getCachedKeepLatest(PracticePlayer|string $player);

	public function refresh(PracticePlayer|string $player) : \Generator;

	public function reset(PracticePlayer|string $player) : \Generator;

	/**
	 * @param T $data
	 */
	public function set(PracticePlayer|string $player, $data) : \Generator|bool;

	public function delete(PracticePlayer|string $player) : \Generator|bool;
}