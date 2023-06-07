<?php


namespace blackjack200\economy;


use blackjack200\economy\provider\await\AwaitMySQLProvider;
use blackjack200\economy\provider\await\AwaitProviderInterface;
use blackjack200\economy\provider\mysql\MySQLProvider;
use blackjack200\economy\provider\ProviderInterface;
use GlobalLogger;
use libasync\await\Await;
use libasync\executor\Executor;
use libasync\executor\ThreadFactory;
use libasync\executor\ThreadPoolExecutor;
use libasync\utils\LoggerUtils;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use Symfony\Component\Filesystem\Path;
use think\DbManager;

class EconomyLoader extends PluginBase {
	private static ?self $instance = null;
	private static ProviderInterface $provider;
	private static AwaitProviderInterface $awaitProvider;
	private ThreadPoolExecutor $executor;

	public static function getInstance() : self {
		return self::$instance;
	}

	public static function getAwaitProvider() : AwaitProviderInterface {
		return self::$awaitProvider;
	}

	public static function getProvider() : ProviderInterface {
		return self::$provider;
	}

	public function getExecutor() : ThreadPoolExecutor {
		return $this->executor;
	}

	public function onEnable() : void {
		self::$instance = $this;
		$autoload = Path::join(__DIR__, '/../../../vendor/autoload.php');
		$this->saveResource('db_config.json');
		$config = file_get_contents(Path::join($this->getDataFolder(), 'db_config.json'));
		$this->executor = self::createThreadPoolExecutor($this, $autoload, $config);
		$this->executor->start();
		self::$provider = new MySQLProvider('player_info', 'player_name');
		self::$awaitProvider = new AwaitMySQLProvider('player_info', 'player_name', $this->executor);
	}

	public static function createThreadPoolExecutor(Plugin $plugin, string $autoload, bool|string $config) : ThreadPoolExecutor {
		return new ThreadPoolExecutor(new ThreadFactory(
			Executor::class, LoggerUtils::makeLogger($plugin), $autoload,
			static function(Executor $e) use ($config) : array {
				$db = new DbManager();
				$db->listen(function($sql, $runtime, $master) {
					$log = static function(?string $s) : void {
						if ($s !== null) {
							GlobalLogger::get()->debug($s);
						}
					};
					$log($sql);
					$log($runtime);
					$log($master);
				});
				$data = json_decode($config, true);
				//TODO is there a better way to do this?
				$data['connections']['mysql']['fields_cache'] = false;
				$data['connections']['mysql']['fields_strict'] = false;
				$db->setConfig($data);
				return [$db];
			},
			static fn($db) => $db->close()
		), 1);
	}

	protected function onDisable() : void {
		$this->executor->shutdown();
	}
}