<?php

namespace blackjack200\economy\provider\next\impl;

use blackjack200\economy\provider\next\impl\types\IdentifierProvider;
use blackjack200\economy\provider\next\impl\types\SchemaConstants;
use think\DbManager;

class StatisticsRepository {
	public const TABLE_STATISTICS_DATA = 'statistics_data';
	public const TABLE_STATISTICS_PLAYER_DATA = 'statistics_player_data';
	public const COL_TYPE = 'type';
	public const COL_DATA = 'data';
	public const COL_STATISTICS_ID = 'id';

	public static function createStatisticsData(DbManager $db, string $type, string $data) : int {
		return $db->table(self::TABLE_STATISTICS_DATA)->insert([
			self::COL_TYPE => $type,
			self::COL_DATA => $data,
		]);
	}

	public static function createStatisticsDataAssociated(DbManager $db, string $type, string $data, array $xuids) : int {
		return $db->transaction(function() use ($db, $type, $data, $xuids) {
			$statisticsId = $db->table(self::TABLE_STATISTICS_DATA)->insertGetId([
				self::COL_TYPE => $type,
				self::COL_DATA => $data,
			]);
			$v = $db->table(SchemaConstants::TABLE_ACCOUNT);
			foreach ($xuids as $xuid) {
				$v = $v->whereOr(SchemaConstants::COL_XUID, $xuid);
			}
			$validatedXuids = $v->find();
			var_dump($validatedXuids);
			$batchData = [];
			foreach ($validatedXuids as $xuid) {
				$batchData[] = [
					SchemaConstants::COL_XUID => $xuid,
					self::COL_STATISTICS_ID => $statisticsId,
				];
			}

			$db->table(self::TABLE_STATISTICS_PLAYER_DATA)->insertAll($batchData);
			return $statisticsId;
		});
	}


	public static function getStatisticsDataById(DbManager $db, int $id) : ?array {
		return $db->table(self::TABLE_STATISTICS_DATA)
			->where(self::COL_STATISTICS_ID, $id)
			->find();
	}

	public static function linkPlayerToStatistics(DbManager $db, IdentifierProvider $id, int $statisticId) : void {
		$id($db, static fn(string $xuid) => $db->table(self::TABLE_STATISTICS_PLAYER_DATA)->insert([
			SchemaConstants::COL_XUID => $xuid,
			self::COL_STATISTICS_ID => $statisticId,
		]));
	}

	public static function getPlayerStatistics(DbManager $db, IdentifierProvider $id) : ?array {
		return $id($db, static fn(string $xuid) => $db->table(self::TABLE_STATISTICS_PLAYER_DATA)
			->alias('spd')
			->join('statistics_data sd', 'spd.id = sd.id')
			->where('spd.xuid', $xuid)
			->select()->toArray()
		);
	}

	public static function deleteStatisticsData(DbManager $db, int $id) : void {
		$db->table(self::TABLE_STATISTICS_DATA)
			->where(self::COL_STATISTICS_ID, $id)
			->delete();
	}
}
