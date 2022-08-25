<?php


namespace blackjack200\economy\provider\mysql;


use blackjack200\economy\EconomyLoader;
use libasync\promise\Promise;

class DBExecutorLauncher {
	private Promise $promise;

	public function __construct(Promise $promise) {
		$this->promise = $promise;
	}

	public function start() : void {
		EconomyLoader::getInstance()->getExecutor()->submit($this->promise);
	}
}