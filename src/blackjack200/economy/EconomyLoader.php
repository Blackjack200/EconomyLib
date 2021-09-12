<?php


namespace blackjack200\economy;


use blackjack200\economy\provider\mysql\MySQLProvider;
use blackjack200\economy\provider\ProviderInterface;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\Utils;
use Webmozart\PathUtil\Path;

class EconomyLoader extends PluginBase {
	private static ?self $instance = null;
	private static ProviderInterface $provider;
	private DBThreadPoolExecutor $executor;

	public function getExecutor() : DBThreadPoolExecutor {
		return $this->executor;
	}

	public static function getInstance() : self {
		return self::$instance;
	}

	public static function getProvider() : ProviderInterface {
		return self::$provider;
	}

	public function onEnable() : void {
		self::$instance = $this;
		$autoload = Path::join(__DIR__, '/../../../vendor/autoload.php');
		$this->saveResource('db_config.json');
		$config = file_get_contents($this->getDataFolder() . 'db_config.json');
		self::$provider = new MySQLProvider('player_info');
		$this->executor = new DBThreadPoolExecutor(Server::getInstance()->getLogger(), Utils::getCoreCount(), $autoload, $config);
		$this->executor->start();
		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(static function () : void {
			EconomyLoader::getInstance()->getExecutor()->mainThreadHeartbeat();
		}), 10);
	}

	protected function onDisable() : void {
		$this->executor->shutdown();
	}
}