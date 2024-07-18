<?php


namespace blackjack200\economy;


use GlobalLogger;
use libasync\AsyncLoader;
use libasync\await\Await;
use libasync\runtime\AsyncExecutionEnvironment;
use libasync\ThreadLocal;
use pocketmine\plugin\PluginBase;
use pocketmine\plugin\PluginDescription;
use pocketmine\plugin\PluginLoader;
use pocketmine\plugin\ResourceProvider;
use pocketmine\Server;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Filesystem\Path;
use think\DbManager;

class EconomyLoader extends PluginBase {
	protected static AsyncExecutionEnvironment $env;
	private static ?self $instance = null;

	public static function getInstance() : self { return self::$instance; }

	public function __construct(PluginLoader $loader, Server $server, PluginDescription $description, string $dataFolder, string $file, ResourceProvider $resourceProvider) {
		self::$instance = $this;
		parent::__construct($loader, $server, $description, $dataFolder, $file, $resourceProvider);
	}

	public function onEnable() : void {
		$autoload = Path::join(__DIR__, '/../../../vendor/autoload.php');
		$this->saveResource('db_config.json');
		$config = file_get_contents(Path::join($this->getDataFolder(), 'db_config.json'));

		self::$env = AsyncExecutionEnvironment::simple(
			static function() use ($autoload, $config) {
				require_once $autoload;

				$threadLocalDb = ThreadLocal::fetch();
				if ($threadLocalDb === null) {
					$db = new DbManager();
					$db->setLog(static function($type, $log) {
						$logger = GlobalLogger::get();
						$logger->debug("[$type] $log");
					});
					$data = json_decode($config, true, 512, JSON_THROW_ON_ERROR);

					$db->setConfig($data);
					$db->setCache(new Psr16Cache(new ArrayAdapter()));
					ThreadLocal::register([$db, 0]);
					return $db;
				}
				var_dump($threadLocalDb[1]);
				return $threadLocalDb[0];
			},
			static function() {
				[$db, $usedCount] = ThreadLocal::fetch();
				assert($db instanceof DbManager);
				if (++$usedCount >= 40) {
					var_dump($usedCount);
					var_dump("CLOSE");
					$db->close();
					ThreadLocal::unregister();
					return;
				}
				ThreadLocal::register([$db, $usedCount]);
			}
		);
	}

	public static function db(\Closure $c) : \Generator {
		return yield from Await::threadify($c, AsyncLoader::getInstance()->getPoolRuntime(), self::$env);
	}
}