<?php


namespace blackjack200\economy\provider\mysql;


use libasync\PromiseAsyncTask;
use pocketmine\utils\MainLogger;
use think\DbManager;

class ThinkPHPTask extends PromiseAsyncTask {
	public static string $autoload;
	public static string $config;

	public function onRun() : void {
		require_once self::$autoload;
		$db = new DbManager();
		$db->listen(function ($sql, $runtime, $master) {
			$this->log($sql);
			$this->log($runtime);
			$this->log($master);
		});
		$db->setConfig(json_decode(self::$config, true));
		while ($this->cal->count() > 0) {
			$value = $this->cal->shift();
			$this->ret = $this->serializeData($value($db));
			if ($this->ret === self::EXECUTE_DROP) {
				break;
			}
		}
		$db->close();
	}

	public function log(?string $val) : void {
		if ($val !== null) {
			MainLogger::getLogger()->debug($val);
		}
	}
}