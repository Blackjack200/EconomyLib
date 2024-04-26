<?php

namespace blackjack200\economy\provider\await\column;

use prokits\player\PracticePlayer;


/**
 * @template T
 */
interface ReadonlyCachedData {
	/**
	 * @return T|\Generator
	 */
	public function get(PracticePlayer|string $player);

	/**
	 * @return T|\Generator
	 */
	public function getCached(PracticePlayer|string $player);

	/**
	 * @return T
	 */
	public function readCached(PracticePlayer|string $player);

	/**
	 * @return T|\Generator
	 */
	public function getCachedKeepLatest(PracticePlayer|string $player);

	public function refresh(PracticePlayer|string $player) : \Generator;
}