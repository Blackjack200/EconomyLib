<?php


namespace blackjack200\economy\provider\mysql;


use libasync\ArgInfo;
use libasync\PromiseAsyncTask;
use think\DbManager;

class ThinkPHPTask extends PromiseAsyncTask {
	public static string $autoload;
	public static string $config;

	protected function getExtraArgs() : array {
		require_once self::$autoload;
		$db = new DbManager();
		$db->listen(function ($sql, $runtime, $master) {
			$this->log($sql);
			$this->log($runtime);
			$this->log($master);
		});
		$db->setConfig(json_decode(self::$config, true));
		return [new ArgInfo($db, static function () use ($db) : void {
			$db->close();
		})];
	}

	public function log(?string $val) : void {
		if ($val !== null) {
			\GlobalLogger::get()->debug($val);
		}
	}
}