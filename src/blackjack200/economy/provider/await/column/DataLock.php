<?php

namespace blackjack200\economy\provider\await\column;

class DataLock {
	private readonly float $creationTime;

	public function __construct() {
		$this->creationTime = microtime(true);
	}

	public function getCreationTime() : float { return $this->creationTime; }
}