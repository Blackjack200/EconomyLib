<?php


namespace blackjack200\economy;


use blackjack200\economy\provider\mysql\MySQLProvider;
use blackjack200\economy\provider\ProviderInterface;
use GlobalLogger;
use libasync\executor\Executor;
use libasync\executor\ThreadFactory;
use libasync\executor\ThreadPoolExecutor;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Utils;
use think\DbManager;
use Webmozart\PathUtil\Path;

class EconomyLoader extends PluginBase {
	private static ?self $instance = null;
	private static ProviderInterface $provider;
	private ThreadPoolExecutor $executor;

	public function getExecutor() : ThreadPoolExecutor {
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
		$this->executor = new ThreadPoolExecutor(new ThreadFactory(
			Executor::class, $this->getLogger(), $autoload,
			static function (Executor $e) use ($config) : array {
				$db = new DbManager();
				$db->listen(function ($sql, $runtime, $master) {
					$log = static function (?string $s) : void {
						if ($s !== null) {
							GlobalLogger::get()->debug($s);
						}
					};
					$log($sql);
					$log($runtime);
					$log($master);
				});
				$db->setConfig(json_decode($config, true));
				return [$db];
			},
			static fn($db) => $db->close()
		), $this->getScheduler(), (Utils::getCoreCount() >> 1) + 1);
		$this->executor->start();
	}

	protected function onDisable() : void {
		$this->executor->shutdown();
	}
}