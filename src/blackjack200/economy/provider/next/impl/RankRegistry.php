<?php

namespace blackjack200\economy\provider\next\impl;

use blackjack200\economy\provider\next\impl\types\SchemaConstants;
use LogicException;
use think\DbManager;

class RankRegistry {
	public static function getRegistered(DbManager $db) : array {
		return $db->table(SchemaConstants::TABLE_RANK_REG)->select();
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

	public static function get(DbManager $db, string $basename) : ?array {
		return $db->table(SchemaConstants::TABLE_RANK_REG)
			->where(SchemaConstants::COL_RANK_BASENAME, $basename)
			->find();
	}
}