<?php

namespace blackjack200\economy\provider\next\impl;

use blackjack200\economy\provider\next\impl\types\IdentifierProvider;
use blackjack200\economy\provider\next\impl\types\SchemaConstants;
use blackjack200\economy\provider\next\RankServiceProxy;
use blackjack200\economy\provider\UpdateResult;
use LogicException;
use think\db\exception\DbException;
use think\db\Raw;
use think\DbManager;

/**
 * RankService
 *
 * This service manages rank definitions and the assignment of ranks to players.
 *
 * The system includes:
 *
 * 1. **Registered ranks**: Each rank has a unique `basename` and a display name.
 * 2. **Player rank assignment**: Players (identified by XUIDs) can be granted ranks
 *    with optional deadlines. Each XUID+rank combination is unique.
 * 3. **Consistency rules**: Operations are transactional to ensure data integrity.
 * 4. **CRUD operations**: Supports creating, updating, retrieving, and deleting ranks
 *    and player rank assignments.
 *
 * @internal
 * @see RankServiceProxy
 */
class RankService {
	/**
	 * Get all registered ranks.
	 *
	 * Returns an associative array where the key is the rank basename and the value is
	 * the display name.
	 *
	 * @param DbManager $db Database manager instance.
	 * @return array<string, string> Associative array of registered ranks.
	 */
	public static function getRegistered(DbManager $db) : array {
		return $db->table(SchemaConstants::TABLE_RANK_REG)->column(SchemaConstants::COL_RANK_DISPLAY, SchemaConstants::COL_RANK_BASENAME);
	}

	/**
	 * Register a new rank or update an existing one.
	 *
	 * If the rank with the given basename exists, updates its display name.
	 * Otherwise, inserts a new rank.
	 *
	 * @param DbManager $db Database manager instance.
	 * @param string $basename Unique rank identifier.
	 * @param string $display Human-readable display name.
	 * @return UpdateResult Returns `SUCCESS` if inserted or updated, `NO_CHANGE` if unchanged.
	 * @throws LogicException If more than one row is affected during update (should never happen).
	 */
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

	/**
	 * Update the display name of an existing rank.
	 *
	 * If the rank does not exist, returns `NO_CHANGE`.
	 *
	 * @param DbManager $db Database manager instance.
	 * @param string $basename Unique rank identifier.
	 * @param string $newDisplay New human-readable display name.
	 * @return UpdateResult Returns `SUCCESS` if updated, `NO_CHANGE` if rank does not exist.
	 * @throws LogicException If more than one row exists for the rank (should never happen).
	 */
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

	/**
	 * Unregister (delete) a rank by its basename
	 *
	 * @param DbManager $db Database manager instance.
	 * @param string $basename Unique rank identifier.
	 * @return UpdateResult Returns `SUCCESS` if deleted, `NO_CHANGE` if rank did not exist.
	 * @throws DbException
	 */
	public static function unregister(DbManager $db, string $basename) : UpdateResult {
		return UpdateResult::fromRow($db->table(SchemaConstants::TABLE_RANK_REG)
			->where(SchemaConstants::COL_RANK_BASENAME, $basename)
			->delete());
	}

	/**
	 * Assign a rank to a player with an optional expiration deadline.
	 *
	 * If the player already has the rank, updates the deadline. Only valid if the
	 * rank exists in the registry.
	 *
	 * @param DbManager $db Database manager instance.
	 * @param IdentifierProvider $id Identifier resolver for the player.
	 * @param string $rankBasename Rank to assign.
	 * @param int $expiry Timestamp until the rank is valid.
	 * @return UpdateResult Returns `SUCCESS` if inserted or updated, `NO_CHANGE` if rank does not exist.
	 * @throws LogicException If duplicate ranks are detected (should never happen).
	 */
	public static function addRankToPlayer(DbManager $db, IdentifierProvider $id, string $rankBasename, int $expiry) : UpdateResult {
		return $id($db, static function(int $uid) use ($expiry, $rankBasename, $db) : UpdateResult {
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
							SchemaConstants::COL_RANK_DEADLINE => $expiry,
						]));
				}
				return UpdateResult::fromRow($db->table(SchemaConstants::TABLE_RANK_PLAYER_DATA)
					->where(SchemaConstants::COL_XUID, $xuid)
					->where(SchemaConstants::COL_RANK_BASENAME, $rankBasename)
					->update([
						SchemaConstants::COL_XUID => $xuid,
						SchemaConstants::COL_RANK_BASENAME => $rankBasename,
						SchemaConstants::COL_RANK_DEADLINE => $expiry,
					]));
			}
			return UpdateResult::NO_CHANGE;
		}, UpdateResult::INTERNAL_ERROR);
	}

	/**
	 * Remove a rank from a player.
	 *
	 * @param DbManager $db Database manager instance.
	 * @param IdentifierProvider $id Identifier resolver for the player.
	 * @param string $rankBasename Rank to remove.
	 * @return UpdateResult Returns `SUCCESS` if removed, `NO_CHANGE` if the player did not have the rank.
	 */
	public static function removeRankFromPlayer(DbManager $db, IdentifierProvider $id, string $rankBasename) : UpdateResult {
		return $id($db, static fn(int $uid) => UpdateResult::fromRow($db->table(SchemaConstants::TABLE_RANK_PLAYER_DATA)
			->where(SchemaConstants::COL_XUID, new Raw(sprintf("(select %s from %s where uid=$uid limit 1)", SchemaConstants::COL_XUID, SchemaConstants::TABLE_ACCOUNT_METADATA)))
			->where(SchemaConstants::COL_RANK_BASENAME, $rankBasename)
			->delete())
			, UpdateResult::INTERNAL_ERROR);
	}

	/**
	 * Retrieve all ranks currently assigned to a player.
	 *
	 * Returns an associative array where the key is the rank basename and the value
	 * is the expiry timestamp.
	 *
	 * @param DbManager $db Database manager instance.
	 * @param IdentifierProvider $id Identifier resolver for the player.
	 * @return array<string, int> Associative array of rank assignments with expires.
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