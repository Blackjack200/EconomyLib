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
	public function getLatest(PracticePlayer|Identity $player);

	/**
	 * @return T|\Generator
	 */
	public function getCached(PracticePlayer|Identity $player);

	/**
	 * @return T
	 */
	public function readCached(PracticePlayer|Identity $player);

	/**
	 * @return T|\Generator
	 */
	public function getCachedKeepLatest(PracticePlayer|Identity $player);

	public function refresh(PracticePlayer|Identity $player) : \Generator;
}