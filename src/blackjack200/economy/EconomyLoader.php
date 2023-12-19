<?php


namespace blackjack200\economy;


use blackjack200\economy\provider\next\AccountMetadataServiceProxy;
use blackjack200\economy\provider\next\impl\AccountDataService;
use blackjack200\economy\provider\next\impl\types\IdentifierProvider;
use GlobalLogger;
use libasync\await\Await;
use libasync\executor\Executor;
use libasync\executor\ThreadFactory;
use libasync\executor\ThreadPoolExecutor;
use libasync\runtime\AsyncExecutionEnvironment;
use libasync\utils\LoggerUtils;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\plugin\PluginDescription;
use pocketmine\plugin\PluginLoader;
use pocketmine\plugin\ResourceProvider;
use pocketmine\Server;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Filesystem\Path;
use think\DbManager;

class EconomyLoader extends PluginBase {
	private static ?self $instance = null;
	private ThreadPoolExecutor $executor;

	public static function getInstance() : self { return self::$instance; }

	public function getExecutor() : ThreadPoolExecutor { return $this->executor; }

	public function __construct(PluginLoader $loader, Server $server, PluginDescription $description, string $dataFolder, string $file, ResourceProvider $resourceProvider) {
		self::$instance = $this;
		parent::__construct($loader, $server, $description, $dataFolder, $file, $resourceProvider);
	}

	public function onEnable() : void {
		$autoload = Path::join(__DIR__, '/../../../vendor/autoload.php');
		$this->saveResource('db_config.json');
		$config = file_get_contents(Path::join($this->getDataFolder(), 'db_config.json'));
		require_once $autoload;
		$this->executor = self::createThreadPoolExecutor($this, $autoload, 'xyron', $config, 2);
		$this->executor->start();
	}

	public static function createThreadPoolExecutor(Plugin $plugin, string $autoload, string $dbName, string $config, int $n = 1) : ThreadPoolExecutor {
		return new ThreadPoolExecutor(new ThreadFactory(
			Executor::class, LoggerUtils::makeLogger($plugin), $autoload,
			AsyncExecutionEnvironment::simple(
				static function() use ($dbName, $config) {
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
					$data = json_decode($config, true, 512, JSON_THROW_ON_ERROR);
					$data['connections']['mysql']['database'] = $dbName;
					$db->setConfig($data);
					$db->setCache(new Psr16Cache(PhpArrayAdapter::create('cache.dat', new ArrayAdapter(60))));
					return $db;
				},
				static fn($db) => $db->close()
			)
		), $n);
	}

	protected function onDisable() : void {
		$this->executor->shutdown();
	}
}