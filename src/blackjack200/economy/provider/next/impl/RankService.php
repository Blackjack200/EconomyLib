<?php

namespace blackjack200\economy\provider\next\impl;

use blackjack200\economy\provider\next\impl\types\IdentifierProvider;
use blackjack200\economy\provider\next\impl\types\SchemaConstants;
use LogicException;
use think\DbManager;

class RankService {

	public static function addRankToPlayer(DbManager $db, IdentifierProvider $id, string $rankBasename) : void {
		$id($db, static function(string $xuid) use ($rankBasename, $db) : void {
			$result = $db->table(SchemaConstants::TABLE_RANK_PLAYER_DATA)
				->where(SchemaConstants::COL_XUID, $xuid)
				->where(SchemaConstants::COL_RANK_BASENAME, $rankBasename)
				->select();
			$rankExists = $db->table(SchemaConstants::TABLE_RANK_REG)
				->where(SchemaConstants::COL_RANK_BASENAME, $rankBasename)
				->select();
			if (count($rankExists) > 1) {
				throw new LogicException("This should never happens. Rank $rankBasename, " . count($rankExists));
			}
			if (count($result) === 0 && count($rankExists) === 1) {
				$db->table(SchemaConstants::TABLE_RANK_PLAYER_DATA)
					->insert([
						SchemaConstants::COL_XUID => $xuid,
						SchemaConstants::COL_RANK_BASENAME => $rankBasename,
					]);
			}
		});
	}

	public static function removeRankFromPlayer(DbManager $db, IdentifierProvider $id, string $rankBasename) : void {
		$id($db, static fn(string $xuid) => $db->table(SchemaConstants::TABLE_RANK_PLAYER_DATA)
			->where(SchemaConstants::COL_XUID, $xuid)
			->where(SchemaConstants::COL_RANK_BASENAME, $rankBasename)
			->delete()
		);
	}
}