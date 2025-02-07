<?php

namespace blackjack200\economy\provider\next\impl;

use blackjack200\economy\provider\next\impl\types\IdentifierProvider;
use blackjack200\economy\provider\next\impl\types\SchemaConstants;
use blackjack200\economy\provider\UpdateResult;
use LogicException;
use think\db\Raw;
use think\DbManager;

class RankService {
	public static function getRegistered(DbManager $db) : array {
		return $db->table(SchemaConstants::TABLE_RANK_REG)->column(SchemaConstants::COL_RANK_DISPLAY, SchemaConstants::COL_RANK_BASENAME);
	}

	public static function register(DbManager $db, string $basename, string $display) : UpdateResult {
		return $db->transaction(static function() use ($display, $basename, $db) {
			$result = $db->table(SchemaConstants::TABLE_RANK_REG)
				->where(SchemaConstants::COL_RANK_BASENAME, $basename)
				->select();
			if (count($result) !== 0) {
				$updated = $db->table(SchemaConstants::TABLE_RANK_REG)
					->where(SchemaConstants::COL_RANK_BASENAME, $basename)
					->update([SchemaConstants::COL_RANK_DISPLAY => $display]);
				if ($updated > 1) {
					throw new LogicException("This should never happens. $basename '$display' $updated");
				}
				return UpdateResult::fromRow($updated);
			}
			return UpdateResult::fromRow($db->table(SchemaConstants::TABLE_RANK_REG)->insert([
				SchemaConstants::COL_RANK_BASENAME => $basename,
				SchemaConstants::COL_RANK_DISPLAY => $display,
			]));
		});
	}

	public static function set(DbManager $db, string $basename, string $newDisplay) : UpdateResult {
		return $db->transaction(static function() use ($newDisplay, $db, $basename) : UpdateResult {
			$rankExists = $db->table(SchemaConstants::TABLE_RANK_REG)
				->where(SchemaConstants::COL_RANK_BASENAME, $basename)
				->select()
				->count();
			if ($rankExists > 1) {
				throw new LogicException("This should never happens. Rank $basename, count($rankExists)");
			}
			if ($rankExists !== 0) {
				return UpdateResult::fromRow($db->table(SchemaConstants::TABLE_RANK_REG)
					->where(SchemaConstants::COL_RANK_BASENAME, $basename)
					->update([SchemaConstants::COL_RANK_DISPLAY => $newDisplay]));
			}
			return UpdateResult::NO_CHANGE;
		});
	}

	public static function unregister(DbManager $db, string $basename) : UpdateResult {
		return UpdateResult::fromRow($db->table(SchemaConstants::TABLE_RANK_REG)
			->where(SchemaConstants::COL_RANK_BASENAME, $basename)
			->delete());
	}

	public static function addRankToPlayer(DbManager $db, IdentifierProvider $id, string $rankBasename, int $deadline) : UpdateResult {
		return $id($db, static function(int $uid) use ($deadline, $rankBasename, $db) : UpdateResult {
			$xuid = new Raw(sprintf("(select %s from %s where uid=$uid limit 1)", SchemaConstants::COL_XUID, SchemaConstants::TABLE_ACCOUNT_METADATA));
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
			if (count($rankExists) === 1) {
				if (count($result) === 0) {
					return UpdateResult::fromRow($db->table(SchemaConstants::TABLE_RANK_PLAYER_DATA)
						->insert([
							SchemaConstants::COL_XUID => $xuid,
							SchemaConstants::COL_RANK_BASENAME => $rankBasename,
							SchemaConstants::COL_RANK_DEADLINE => $deadline,
						]));
				}
				return UpdateResult::fromRow($db->table(SchemaConstants::TABLE_RANK_PLAYER_DATA)
					->where(SchemaConstants::COL_XUID, $xuid)
					->where(SchemaConstants::COL_RANK_BASENAME, $rankBasename)
					->update([
						SchemaConstants::COL_XUID => $xuid,
						SchemaConstants::COL_RANK_BASENAME => $rankBasename,
						SchemaConstants::COL_RANK_DEADLINE => $deadline,
					]));
			}
			return UpdateResult::NO_CHANGE;
		}, UpdateResult::INTERNAL_ERROR);
	}

	public static function removeRankFromPlayer(DbManager $db, IdentifierProvider $id, string $rankBasename) : UpdateResult {
		return $id($db, static fn(int $uid) => UpdateResult::fromRow($db->table(SchemaConstants::TABLE_RANK_PLAYER_DATA)
			->where(SchemaConstants::COL_XUID, new Raw(sprintf("(select %s from %s where uid=$uid limit 1)", SchemaConstants::COL_XUID, SchemaConstants::TABLE_ACCOUNT_METADATA)))
			->where(SchemaConstants::COL_RANK_BASENAME, $rankBasename)
			->delete())
			, UpdateResult::INTERNAL_ERROR);
	}

	/**
	 * @return array<string, int>
	 */
	public static function getRanksFromPlayer(DbManager $db, IdentifierProvider $id) : array {
		$results = $id($db, static fn(int $uid) => $db->table(SchemaConstants::TABLE_RANK_PLAYER_DATA)
			->where(SchemaConstants::COL_XUID, new Raw(sprintf("(select %s from %s where uid=$uid limit 1)", SchemaConstants::COL_XUID, SchemaConstants::TABLE_ACCOUNT_METADATA)))
			->column([SchemaConstants::COL_RANK_BASENAME, SchemaConstants::COL_RANK_DEADLINE])
			, []);
		$merged = [];
		//compound primary key (xuid,basename) is constrained to be unique
		//so there is no duplicated entry.
		foreach ($results as $result) {
			[$basename, $deadline] = [$result[SchemaConstants::COL_RANK_BASENAME], $result[SchemaConstants::COL_RANK_DEADLINE]];
			$merged[$basename] = $deadline;
		}
		return $merged;
	}
}