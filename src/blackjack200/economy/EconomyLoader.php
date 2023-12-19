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
		/*Await::do(static function() : void {
			AccountServiceAsync::register('2535468735826146', 'IPlayfordev');
			AccountServiceAsync::register('abc', 'Bob');
			$xuid = AccountServiceAsync::getXuid('IPlayfordev');
			var_dump($xuid);
			$id = StatisticsRepositoryAsync::createStatisticsDataAssociated('test', JsonUtils::encode([
				'winner' => '2535468735826146',
				'loser' => 'abc',
			]), [
				'2535468735826146',
				'abc',
				'vvew'
			]);
			var_dump($id);
			var_dump(StatisticsRepositoryAsync::getStatisticsDataById($id));
			var_dump(StatisticsRepositoryAsync::getPlayerStatistics(IdentifierProvider::name('Bob')));
			var_dump(StatisticsRepositoryAsync::getPlayerStatistics(IdentifierProvider::name('IPlayfordev')));
			var_dump(StatisticsRepositoryAsync::getPlayerStatistics(IdentifierProvider::name('dcvwvd')));
		/*
				$id = IdentifierProvider::name('IPlayfordev');
				Await::do(function() use ($id) : void {
					var_dump(Await::async(static fn($db) => AccountDataService::getAll($db, $id), $this->getExecutor()));
				})->panic();
				Await::future(fn() => Await::async(static fn($db) => AccountDataService::setAll($db, $id, ThreadSafeArray::fromArray(['test_counter'=>0])), $this->getExecutor()))->get();
				$futures = [];
				for ($i = 2048; $i > 0; $i--) {
					$futures[] = Await::future(fn() => Await::async(static fn($db) => AccountDataService::update($db, $id, 'test_counter', static fn($old) => $old + 1), $this->getExecutor()));
				}
				while (count($futures) > 0) {
					foreach ($futures as $k => $f) {
						if ($f->isDone()) {
							unset($futures[$k]);
						}
					}
				}
				Await::do(function() use ($id) : void {
					var_dump(Await::async(static fn($db) => AccountDataService::getAll($db, $id), $this->getExecutor()));
				})->panic();
		*/
		Await::do(function() : void {
			AccountMetadataServiceProxy::register('114514', 'FUCK');
			$xuid = AccountMetadataServiceProxy::getXuid('FUCK');
			GlobalLogger::get()->alert("XUID=$xuid");

			$id = IdentifierProvider::name('FUCK');

			$dump = fn() => var_dump(Await::threadify(static fn($db) => AccountDataService::getAll($db, $id), $this->getExecutor()));
			$dump();

			Await::threadify(static fn($db) => AccountDataService::update($db, $id, 'test2', static fn($old) => ((int) $old) + 1), $this->getExecutor());
			$dump();

			Await::threadify(static fn($db) => AccountDataService::update($db, $id, 'test3', static fn($old) => ((int) $old) + 1), $this->getExecutor());
			$dump();

			Await::threadify(static fn($db) => AccountDataService::update($db, $id, 'test4', static fn($old) => ((int) $old) + 1), $this->getExecutor());
			$dump();


			Await::threadify(static fn($db) => AccountDataService::update($db, $id, 'test2', static fn($old) => ((int) $old) + 1), $this->getExecutor());
			$dump();

			//AccountMetadataServiceProxy::delete($id);
		})->panic();
		/*
				Await::do(function() : void {
					$id = IdentifierProvider::xuid('2535468735826146');
					//AccountMetadataServiceProxy::delete($id);
					Await::async(static fn($db) => AccountDataService::register($db, $id), $this->getExecutor());
					Await::async(static fn($db) => AccountDataService::getAll($db, $id), $this->getExecutor());
					Await::async(static fn($db) => AccountDataService::set($db, $id, 'string', 'k', 'v'), $this->getExecutor());
					Await::async(static fn($db) => AccountDataService::set($db, $id, 'int', 'cnt', 1), $this->getExecutor());
					Await::async(static fn($db) => AccountDataService::update($db, $id, 'int', 'counter', static fn($x) => ((int) $x) + 1), $this->getExecutor());
					Await::async(static fn($db) => AccountDataService::update($db, $id, 'int', 'counter', static fn($x) => ((int) $x) + 1), $this->getExecutor());
					Await::async(static fn($db) => AccountDataService::update($db, $id, 'int', 'counter', static fn($x) => ((int) $x) + 1), $this->getExecutor());
					var_dump(Await::async(static fn($db) => AccountDataService::getAll($db, $id), $this->getExecutor()));
					var_dump(Await::async(static fn($db) => iterator_to_array(AccountDataService::sort($db, 'dueln', 100, false)->indexByName(), true), $this->getExecutor()));
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
		$this->executor->shutdown();
	}
}