<?php


namespace blackjack200\economy;


use blackjack200\economy\provider\await\AwaitMySQLProvider;
use blackjack200\economy\provider\await\AwaitProviderInterface;
use GlobalLogger;
use libasync\executor\Executor;
use libasync\executor\ThreadFactory;
use libasync\executor\ThreadPoolExecutor;
use libasync\runtime\AsyncExecutionEnvironment;
use libasync\utils\LoggerUtils;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use Symfony\Component\Filesystem\Path;
use think\DbManager;

class EconomyLoader extends PluginBase {
	private static ?self $instance = null;
	private static AwaitProviderInterface $awaitProvider;
	private ThreadPoolExecutor $executor;

	public static function getInstance() : self {
		return self::$instance;
	}

	public static function getAwaitProvider() : AwaitProviderInterface {
		return self::$awaitProvider;
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
		self::$awaitProvider = new AwaitMySQLProvider('player_info', 'player_name', $this->executor);
	}

	public static function createThreadPoolExecutor(Plugin $plugin, string $autoload, bool|string $config) : ThreadPoolExecutor {
		return new ThreadPoolExecutor(new ThreadFactory(
			Executor::class, LoggerUtils::makeLogger($plugin), $autoload,
			AsyncExecutionEnvironment::simple(
				static function() use ($config) {
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
					//TODO is there a better way to do this?
					$data['connections']['mysql']['fields_cache'] = false;
					$data['connections']['mysql']['fields_strict'] = false;
					$db->setConfig($data);
					return $db;
				},
				static fn($db) => $db->close()
			)
		), 2);
	}

	protected function onDisable() : void {
		$this->executor->shutdown();
	}
}