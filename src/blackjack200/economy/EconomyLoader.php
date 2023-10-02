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
	private ThreadPoolExecutor $xyronExecutor;

	public static function getInstance() : self { return self::$instance; }

	public function getExecutor() : ThreadPoolExecutor { return $this->xyronExecutor; }

	public function getXyronExecutor() : ThreadPoolExecutor { return $this->xyronExecutor; }

	public function __construct(PluginLoader $loader, Server $server, PluginDescription $description, string $dataFolder, string $file, ResourceProvider $resourceProvider) {
		self::$instance = $this;
		parent::__construct($loader, $server, $description, $dataFolder, $file, $resourceProvider);
	}

	public function onEnable() : void {
		$autoload = Path::join(__DIR__, '/../../../vendor/autoload.php');
		$this->saveResource('db_config.json');
		$config = file_get_contents(Path::join($this->getDataFolder(), 'db_config.json'));
		require_once $autoload;
		$this->xyronExecutor = self::createThreadPoolExecutor($this, $autoload, 'xyron', $config, 2);
		$this->xyronExecutor->start();
		/*
				$id = IdentifierProvider::name('IPlayfordev');

				Await::do(function() use ($id) : void {
					var_dump(Await::async(static fn($db) => AccountDataService::getAll($db, $id), $this->getXyronExecutor()));
				})->panic();

				Await::future(fn() => Await::async(static fn($db) => AccountDataService::setAll($db, $id, ThreadSafeArray::fromArray(['test_counter'=>0])), $this->getXyronExecutor()))->get();

				$futures = [];
				for ($i = 2048; $i > 0; $i--) {
					$futures[] = Await::future(fn() => Await::async(static fn($db) => AccountDataService::update($db, $id, 'test_counter', static fn($old) => $old + 1), $this->getXyronExecutor()));
				}
				while (count($futures) > 0) {
					foreach ($futures as $k => $f) {
						if ($f->isDone()) {
							unset($futures[$k]);
						}
					}
				}
				Await::do(function() use ($id) : void {
					var_dump(Await::async(static fn($db) => AccountDataService::getAll($db, $id), $this->getXyronExecutor()));
				})->panic();
		*/
		/*Await::do(function() : void {
			AccountMetadataServiceProxy::register('2535468735826146', 'IPlayfordev');
			$xuid = AccountMetadataServiceProxy::getXuid('IPlayfordev');
			GlobalLogger::get()->alert("XUID=$xuid");
			$id = IdentifierProvider::name('IPlayfordev');
			Await::async(static fn($db) => AccountDataService::register($db, $id), $this->getXyronExecutor());
			var_dump(Await::async(static fn($db) => AccountDataService::getAll($db, $id), $this->getXyronExecutor()));
			Await::async(static fn($db) => AccountDataService::update($db, $id, 'test2', static fn() => 1), $this->getXyronExecutor());
			var_dump(Await::async(static fn($db) => AccountDataService::getAll($db, $id), $this->getXyronExecutor()));
			/*var_dump(RankServiceProxy::getRegistered());
			RankServiceProxy::register('test_ng', 'NG');
			RankServiceProxy::register('test_xyr', 'Xyron');
			var_dump(RankServiceProxy::getRegistered());
			var_dump(RankServiceProxy::getRanksFromPlayer($id));
			RankServiceProxy::addRankToPlayer($id, 'test2', time());
			RankServiceProxy::addRankToPlayer($id, 'test_ng', time());
			var_dump(RankServiceProxy::getRanksFromPlayer($id));
		*//*
		})->panic();*/
/*
		Await::do(function() : void {
			$changed = AccountMetadataServiceProxy::fixXuidNameAssociation('2535468735826146', 'IPlayfordev');
			var_dump($changed);
			$id = IdentifierProvider::xuid('2535468735826146');
			//AccountMetadataServiceProxy::delete($id);

			//Await::async(static fn($db) => AccountDataService::register($db, $id), $this->getXyronExecutor());
			var_dump(Await::async(static fn($db) => AccountDataService::getAll($db, $id), $this->getXyronExecutor()));
			var_dump(Await::async(static fn($db) => AccountDataService::delete($db, $id,'k'), $this->getXyronExecutor()));
			//Await::async(static fn($db) => AccountDataService::set($db, $id, 'string', 'k', 'v'), $this->getXyronExecutor());
			//Await::async(static fn($db) => AccountDataService::set($db, $id, 'int', 'cnt', 1), $this->getXyronExecutor());
			//Await::async(static fn($db) => AccountDataService::update($db, $id, 'int', 'counter', static fn($x) => ((int) $x) + 1), $this->getXyronExecutor());
			//Await::async(static fn($db) => AccountDataService::update($db, $id, 'int', 'counter', static fn($x) => ((int) $x) + 1), $this->getXyronExecutor());
			//Await::async(static fn($db) => AccountDataService::update($db, $id, 'int', 'counter', static fn($x) => ((int) $x) + 1), $this->getXyronExecutor());
			var_dump(Await::async(static fn($db) => AccountDataService::getAll($db, $id), $this->getXyronExecutor()));

			var_dump(Await::async(static fn($db) => iterator_to_array(AccountDataService::sort($db, 'cnt', 100, false)->indexByName(), true), $this->getXyronExecutor()));
		})->panic();*/
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
		$this->xyronExecutor->shutdown();
	}
}