<?php


namespace blackjack200\economy;


use GlobalLogger;
use libasync\InterruptSignal;
use libasync\Promise;
use Logger;
use pocketmine\thread\Thread;
use think\DbManager;
use Threaded;
use Throwable;
use Volatile;

class DBThreadExecutor extends Thread {
	public string $config;
	public string $autoload;
	private Threaded $queue;
	private Threaded $finished;
	private static array $promiseMap = [];
	private Logger $logger;

	public function __construct(Logger $logger, string $autoload, string $config, Volatile $queue) {
		$this->logger = $logger;
		$this->autoload = $autoload;
		$this->config = $config;
		$this->queue = $queue;
		$this->finished = new Threaded();
	}

	public function log(?string $val) : void {
		if ($val !== null) {
			GlobalLogger::get()->debug($val);
		}
	}

	protected function onRun() : void {
		GlobalLogger::set($this->logger);
		require_once $this->autoload;
		$db = new DbManager();
		$db->listen(function ($sql, $runtime, $master) {
			$this->log($sql);
			$this->log($runtime);
			$this->log($master);
		});
		$db->setConfig(json_decode($this->config, true));
		GlobalLogger::get()->debug('DB executor started');
		$tick = 0;
		while (!$this->isKilled) {
			$this->executeTasks($db);
			usleep(50);
			if ($tick++ === 60000) {
				gc_enable();
				gc_collect_cycles();
				gc_mem_caches();
				//GlobalLogger::get()->debug('Trigger GC');
				$tick = 0;
			}
		}
		$db->close();
		GlobalLogger::get()->debug('DB executor shutdown gracefully');
	}

	protected function executeTasks(DbManager $db) : void {
		$result = null;
		$rejected = true;
		$error = false;
		$reject = static function (...$reason) use (&$result) : void {
			$result = igbinary_serialize($reason);
			throw new InterruptSignal();
		};
		$resolve = static function (...$reason) use (&$rejected, &$result) : void {
			$result = igbinary_serialize($reason);
			$rejected = false;
			throw new InterruptSignal();
		};
		while ($this->queue->count() > 0) {
			[$cal, $hash] = $this->queue->shift();
			try {
				$cal($resolve, $reject, $db);
			} catch (Throwable $e) {
				if (!$e instanceof InterruptSignal) {
					GlobalLogger::get()->logException($e);
					$error = true;
				}
			}
			$this->finished[] = igbinary_serialize([$hash, $error, $rejected, $result]);
		}
	}

	public function mainThreadHeartbeat() : void {
		while ($this->finished->count() > 0) {
			[$hash, $error, $rejected, $result] = igbinary_unserialize($this->finished->shift());
			/** @var Promise $promise */
			$promise = self::$promiseMap[$hash];
			if (!$error) {
				$this->executePromiseCallbacks($rejected, $promise, $result);
			}
			unset(self::$promiseMap[$hash]);
		}
	}

	public function submit(Promise $promise) : void {
		$hash = spl_object_hash($promise);
		self::$promiseMap[$hash] = $promise;
		$this->queue[] = [$promise->getAsyncCall(), $hash];
	}

	private function executePromiseCallbacks($rejected, Promise $promise, $result) : void {
		if ($rejected) {
			$callbacks = $promise->getRejectedCallbacks();
		} else {
			$callbacks = $promise->getFulfillCallbacks();
		}
		foreach ($callbacks as $callback) {
			$callback(...igbinary_unserialize($result));
		}
	}
}