<?php

namespace blackjack200\economy\provider\await\column;

use blackjack200\economy\provider\next\impl\types\Identity;
use prokits\player\PracticePlayer;


/**
 * @template T
 */
interface ReadonlyCachedData {
	/**
	 * @return T|\Generator
	 */
	public function getLatest(PracticePlayer|Identity|string $player);

	/**
	 * @return T|\Generator
	 */
	public function getCached(PracticePlayer|Identity|string $player);

	/**
	 * @return T
	 */
	public function readCached(PracticePlayer|Identity|string $player);

	/**
	 * @return T|\Generator
	 */
	public function getCachedKeepLatest(PracticePlayer|Identity|string $player);

	public function refresh(PracticePlayer|Identity|string $player) : \Generator;
}