<?php


namespace blackjack200\economy;


use GlobalLogger;
use libasync\executor\ExecutorPool;
use libasync\executor\ExecutorWorker;
use libasync\executor\WorkerFactory;
use libasync\runtime\AsyncExecutionEnvironment;
use libasync\utils\LoggerUtils;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\plugin\PluginDescription;
use pocketmine\plugin\PluginLoader;
use pocketmine\plugin\ResourceProvider;
use pocketmine\Server;
use pocketmine\utils\Filesystem;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Filesystem\Path;
use think\DbManager;

class EconomyLoader extends PluginBase {
	private static ?self $instance = null;
	protected $cacheDir;
	private ExecutorPool $executor;

	public static function getInstance() : self { return self::$instance; }

	public function getExecutor() : ExecutorPool { return $this->executor; }

	public function __construct(PluginLoader $loader, Server $server, PluginDescription $description, string $dataFolder, string $file, ResourceProvider $resourceProvider) {
		self::$instance = $this;
		parent::__construct($loader, $server, $description, $dataFolder, $file, $resourceProvider);
	}

	public function onEnable() : void {
		$autoload = Path::join(__DIR__, '/../../../vendor/autoload.php');
		$this->saveResource('db_config.json');
		$config = file_get_contents(Path::join($this->getDataFolder(), 'db_config.json'));
		$this->cacheDir = Path::join(Server::getInstance()->getDataPath(), "cache");
		$this->executor = self::createThreadPoolExecutor($this, $autoload, $this->cacheDir, $config, 4);
		//$this->executor->start();
	}

	public static function createThreadPoolExecutor(
		Plugin $plugin,
		string $autoload,
		string $cacheDir,
		string $config,
		int    $n = 1
	) : ExecutorPool {
		return new ExecutorPool(Server::getInstance()->getTickSleeper(), new WorkerFactory(
			ExecutorWorker::class, LoggerUtils::makeLogger($plugin), $autoload,
			AsyncExecutionEnvironment::simple(
				static function() use ($cacheDir, $config) {
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

					$db->setConfig($data);
					$db->setCache(new Psr16Cache(new FilesystemAdapter("db", 30, $cacheDir)));
					return $db;
				},
				static fn($db) => $db->close()
			)
		), $n);
	}

	protected function onDisable() : void {
		//$this->executor->shutdown();
		Filesystem::recursiveUnlink($this->cacheDir);
	}
}