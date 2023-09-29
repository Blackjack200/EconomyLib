<?php

namespace blackjack200\economy\provider\next;

use blackjack200\economy\EconomyLoader;
use blackjack200\economy\provider\next\impl\StatisticsRepository;
use blackjack200\economy\provider\next\impl\types\IdentifierProvider;
use libasync\await\Await;
use think\DbManager;

/**
 * @method static int createStatisticsData(string $type, string $data)
 * @method static int createStatisticsDataAssociated(string $type, string $data, array $xuids)
 * @method static array|null getStatisticsDataById(int $id)
 * @method static void linkPlayerToStatistics(IdentifierProvider $id, int $statisticId)
 * @method static array|null getPlayerStatistics(IdentifierProvider $id)
 * @method static void deleteStatisticsData(int $id)
 */
class StatisticsRepositoryAsync {
	public static function __callStatic(string $name, array $arguments) {
		return Await::async(static fn(DbManager $db) => StatisticsRepository::$name($db, ...$arguments), EconomyLoader::getInstance()->getXyronExecutor());
	}
}