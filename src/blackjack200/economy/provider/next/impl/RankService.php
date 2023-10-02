<?php

namespace blackjack200\economy\provider\next\impl;

use blackjack200\economy\provider\next\impl\types\IdentifierProvider;
use blackjack200\economy\provider\next\impl\types\SchemaConstants;
use DateTime;
use LogicException;
use think\DbManager;

class RankService {
	public static function getRegistered(DbManager $db) : array {
		return $db->table(SchemaConstants::TABLE_RANK_REG)->column(SchemaConstants::COL_RANK_DISPLAY, SchemaConstants::COL_RANK_BASENAME);
	}

	public static function register(DbManager $db, string $basename, string $display) : void {
		$db->transaction(static function() use ($display, $basename, $db) {
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
				return;
			}
			$db->table(SchemaConstants::TABLE_RANK_REG)->insert([
				SchemaConstants::COL_RANK_BASENAME => $basename,
				SchemaConstants::COL_RANK_DISPLAY => $display,
			]);
		});
	}

	public static function set(DbManager $db, string $basename, string $newDisplay) : void {
		$db->transaction(static function() use ($newDisplay, $db, $basename) : void {
			$rankExists = $db->table(SchemaConstants::TABLE_RANK_REG)
				->where(SchemaConstants::COL_RANK_BASENAME, $basename)
				->select()
				->count();
			if ($rankExists > 1) {
				throw new LogicException("This should never happens. Rank $basename, count($rankExists)");
			}
			if ($rankExists !== 0) {
				$db->table(SchemaConstants::TABLE_RANK_REG)
					->where(SchemaConstants::COL_RANK_BASENAME, $basename)
					->update([SchemaConstants::COL_RANK_DISPLAY => $newDisplay]);
			}
		});
	}

	public static function unregister(DbManager $db, string $basename) : void {
		$db->table(SchemaConstants::TABLE_RANK_REG)
			->where(SchemaConstants::COL_RANK_BASENAME, $basename)
			->delete();
	}

	public static function addRankToPlayer(DbManager $db, IdentifierProvider $id, string $rankBasename, DateTime|int $deadline) : void {
		if ($deadline instanceof DateTime) {
			$deadline = $deadline->getTimestamp();
		}
		$id($db, static function(string $xuid) use ($deadline, $rankBasename, $db) : void {
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
						SchemaConstants::COL_RANK_DEADLINE => $deadline,
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

	/**
	 * @return array<string, \DateTime>
	 */
	public static function getRanksFromPlayer(DbManager $db, IdentifierProvider $id) : array {
		$results = $id($db, static fn(string $xuid) => $db->table(SchemaConstants::TABLE_RANK_PLAYER_DATA)
			->where(SchemaConstants::COL_XUID, $xuid)
			->column([SchemaConstants::COL_RANK_BASENAME, SchemaConstants::COL_RANK_DEADLINE])
		);
		$merged = [];
		//compound primary key (xuid,basename) is constrained to be unique
		//so there is no duplicated entry.
		foreach ($results as $result) {
			[$basename, $deadline] = [$result[SchemaConstants::COL_RANK_BASENAME], $result[SchemaConstants::COL_RANK_DEADLINE]];
			$date = new DateTime();
			$date->setTimestamp($deadline);
			$merged[$basename] = $date;
		}
		return $merged;
	}
}