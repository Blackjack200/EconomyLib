<?php


namespace blackjack200\economy;


use blackjack200\economy\provider\mysql\MySQLProvider;
use blackjack200\economy\provider\mysql\ThinkPHPTask;
use blackjack200\economy\provider\ProviderInterface;
use pocketmine\plugin\PluginBase;

class EconomyLoader extends PluginBase {
	private static ?self $instance = null;
	private ProviderInterface $provider;

	public static function getInstance() : self {
		return self::$instance;
	}

	public function onEnable() : void {
		self::$instance = $this;
		$autoload = __DIR__ . '/../../../vendor/autoload.php';
		ThinkPHPTask::$autoload = $autoload;
		require_once $autoload;
		$this->saveResource('db_config.json', true);
		ThinkPHPTask::$config = file_get_contents($this->getDataFolder() . 'db_config.json');
		$this->provider = new MySQLProvider();
	}

	public function getProvider() : ProviderInterface {
		return $this->provider;
	}
}