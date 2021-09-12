<?php


namespace blackjack200\economy;


use libasync\Promise;
use ThreadedLogger;
use Volatile;

class DBThreadPoolExecutor {
	private int $threadCount;
	/** @var DBThreadExecutor[] */
	private array $threads = [];

	public function __construct(ThreadedLogger $logger, int $threadCount, string $autoload, string $config) {
		$this->threadCount = $threadCount;
		for ($i = 1; $i <= $threadCount; $i++) {
			$this->threads[] = new DBThreadExecutor(new \PrefixedLogger($logger, "THREAD#$i"), $autoload, $config, new Volatile());
		}
	}

	public function getThreadCount() : int {
		return $this->threadCount;
	}

	public function start() : void {
		foreach ($this->threads as $thread) {
			$thread->start();
		}
	}

	public function shutdown() : void {
		foreach ($this->threads as $thread) {
			$thread->quit();
		}
	}

	public function submit(Promise $promise) : void {
		$this->threads[array_rand($this->threads)]->submit($promise);
	}

	public function mainThreadHeartbeat() : void {
		foreach ($this->threads as $thread) {
			$thread->mainThreadHeartbeat();
		}
	}
}
