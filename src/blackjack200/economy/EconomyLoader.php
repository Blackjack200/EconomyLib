<?php


namespace blackjack200\economy;


use blackjack200\economy\provider\next\AccountServiceAsync;
use blackjack200\economy\provider\next\impl\types\IdentifierProvider;
use blackjack200\economy\provider\next\StatisticsRepositoryAsync;
use GlobalLogger;
use libasync\await\Await;
use libasync\executor\Executor;
use libasync\executor\ThreadFactory;
use libasync\executor\ThreadPoolExecutor;
use libasync\runtime\AsyncExecutionEnvironment;
use libasync\utils\LoggerUtils;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use prokits\utils\JsonUtils;
use Symfony\Component\Filesystem\Path;
use think\DbManager;

class EconomyLoader extends PluginBase {
	private static ?self $instance = null;
	private ThreadPoolExecutor $xyronExecutor;
	private ThreadPoolExecutor $executor;

	public static function getInstance() : self { return self::$instance; }

	public function getExecutor() : ThreadPoolExecutor { return $this->executor; }

	public function getXyronExecutor() : ThreadPoolExecutor { return $this->xyronExecutor; }

	public function onEnable() : void {
		self::$instance = $this;
		$autoload = Path::join(__DIR__, '/../../../vendor/autoload.php');
		$this->saveResource('db_config.json', true);
		$config = file_get_contents(Path::join($this->getDataFolder(), 'db_config.json'));
		require_once $autoload;
		$this->executor = self::createThreadPoolExecutor($this, $autoload, 'mirekits', $config);
		$this->executor->start();
		$this->xyronExecutor = self::createThreadPoolExecutor($this, $autoload, 'xyron', $config);
		$this->xyronExecutor->start();
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
		})->panic();*/
	}

	public static function createThreadPoolExecutor(Plugin $plugin, string $autoload, string $dbName, bool|string $config) : ThreadPoolExecutor {
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
					//TODO is there a better way to do this?
					$data['connections']['mysql']['fields_cache'] = false;
					$data['connections']['mysql']['fields_strict'] = false;
					$data['connections']['mysql']['database'] = $dbName;
					$db->setConfig($data);
					return $db;
				},
				static fn($db) => $db->close()
			)
		), 2);
	}

	protected function onDisable() : void {
		$this->executor->shutdown();
		$this->xyronExecutor->shutdown();
	}
}