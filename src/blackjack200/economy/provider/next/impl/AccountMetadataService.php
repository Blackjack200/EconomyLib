<?php

namespace blackjack200\economy\provider\next\impl;

use blackjack200\economy\provider\next\AccountMetadataServiceProxy;
use blackjack200\economy\provider\next\impl\types\IdentifierProvider;
use blackjack200\economy\provider\next\impl\types\SchemaConstants;
use blackjack200\economy\provider\UpdateResult;
use think\DbManager;

/**
 * AccountMetadataService
 *
 * This service manages the mapping and consistency between player identifiers (XUIDs)
 * and human-readable player names within the account system.
 *
 * The system enforces the following rules:
 *
 * 1. **XUID uniqueness**: Each XUID represents a unique account and should not be
 *    associated with multiple player names. Multiple names for the same XUID are
 *    possible temporarily but are resolved through `fixXuidNameAssociation`.
 *
 * 2. **Name lookup**: Player names are not globally unique, but each name can be
 *    resolved to the most recent XUID. In cases where a name has been reused,
 *    the latest entry (by last modified timestamp) is returned.
 *
 * 3. **Registration flow**: Accounts can be registered with an optional XUID and
 *    a required player name. If an account already exists (XUID or XUID+name),
 *    registration returns `NO_CHANGE`. Otherwise, a new record is inserted.
 *
 * 4. **Consistency enforcement**: The `fixXuidNameAssociation` method ensures that
 *    a given XUID is associated with the correct player name. It optionally
 *    registers the account if it does not exist, and updates any mismatched
 *    records to maintain consistency.
 *
 * 5. **Transactional safety**: All database-altering operations are executed inside
 *    transactions to guarantee atomicity and prevent partial updates.
 *
 * This design enables a robust and audit-friendly mapping between stable identifiers
 * (XUIDs) and mutable player names, supporting operations such as retrieval, deletion,
 * and correction of mismatched associations.
 *
 * @internal
 * @see AccountMetadataServiceProxy
 */
class AccountMetadataService {
	/**
	 * Register a new account with XUID and player name.
	 *
	 * If the account already exists (matching XUID or XUID+name if XUID is null),
	 * returns `NO_CHANGE`. Otherwise, inserts a new record and returns `SUCCESS`.
	 *
	 * The operation is performed inside a database transaction.
	 *
	 * @param DbManager $db Database manager instance.
	 * @param string|null $xuid Optional unique identifier of the account.
	 * @param string $name Player name to register.
	 * @return UpdateResult Returns `SUCCESS` if inserted, `NO_CHANGE` if already exists.
	 */
	public static function register(DbManager $db, ?string $xuid, string $name) : UpdateResult {
		return ($db->transaction(static function() use ($name, $xuid, $db) {
			if ($xuid !== null) {
				$registered = !$db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
					->where(SchemaConstants::COL_XUID, $xuid)
					->select()->isEmpty();
			} else {
				$registered = !$db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
					->where(SchemaConstants::COL_XUID, $xuid)
					->where(SchemaConstants::COL_PLAYER_NAME, $name)
					->select()->isEmpty();
			}
			if ($registered) {
				return UpdateResult::NO_CHANGE;
			}
			$insertedRow = $db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)->insert([
				SchemaConstants::COL_XUID => $xuid,
				SchemaConstants::COL_LAST_MODIFIED_TIME => time(),
				SchemaConstants::COL_PLAYER_NAME => $name,
			]);
			return UpdateResult::fromRow($insertedRow);
		}));
	}

	/**
	 * Delete an account by its UID.
	 *
	 * Uses an `IdentifierProvider` to resolve the UID and performs deletion.
	 *
	 * @param DbManager $db Database manager instance.
	 * @param IdentifierProvider $id Identifier resolver for the account.
	 * @return UpdateResult Returns `SUCCESS` if deleted, `NO_CHANGE` if not found, or `INTERNAL_ERROR`.
	 */
	public static function delete(DbManager $db, IdentifierProvider $id) : UpdateResult {
		return $id($db, static fn(int $uid) => UpdateResult::fromRow($db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
			->where(SchemaConstants::COL_UID, $uid)
			->delete()
		), UpdateResult::INTERNAL_ERROR);
	}

	/**
	 * Get the most recent player name associated with a given XUID.
	 *
	 * If multiple names exist, returns the latest one based on `last_modified_time`.
	 *
	 * @param DbManager $db Database manager instance.
	 * @param string $xuid Account XUID.
	 * @return string|null Returns the player name or null if not found.
	 */
	public static function getName(DbManager $db, string $xuid) : ?string {
		$result = $db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
			->where(SchemaConstants::COL_XUID, $xuid)
			->order(SchemaConstants::COL_LAST_MODIFIED_TIME, 'desc')
			->column(SchemaConstants::COL_PLAYER_NAME);
		/*if (count($result) > 1) {
			throw new LogicException("Xuid: $xuid should not be associated with " . var_export($result, true));
		}*/
		return array_pop($result);
	}

	/**
	 * Get the XUID associated with a given player name.
	 *
	 * Returns the most recently modified entry if multiple exist.
	 *
	 * @param DbManager $db Database manager instance.
	 * @param string $name Player name.
	 * @return string|null Returns the XUID or null if not found.
	 */
	public static function getXuid(DbManager $db, string $name) : ?string {
		$result = $db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
			->where(SchemaConstants::COL_PLAYER_NAME, $name)
			->order(SchemaConstants::COL_LAST_MODIFIED_TIME, 'desc')
			->column(SchemaConstants::COL_XUID);
		/*if (count($result) > 1) {
			throw new LogicException("Name: $name should not be associated with " . var_export($result, true));
		}*/
		return array_pop($result);
	}

	/**
	 * Get the UID for a given XUID and player name combination.
	 *
	 * Returns the most recent entry if multiple exist.
	 *
	 * @param DbManager $db Database manager instance.
	 * @param string|null $xuid Optional XUID of the account.
	 * @param string $name Player name.
	 * @return int|null Returns UID if found, otherwise null.
	 */
	public static function getUid(DbManager $db, ?string $xuid, string $name) : ?int {
		$result = $db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
			->where(SchemaConstants::COL_XUID, $xuid)
			->where(SchemaConstants::COL_PLAYER_NAME, $name)
			->order(SchemaConstants::COL_LAST_MODIFIED_TIME, 'desc')
			->column(SchemaConstants::COL_UID);
		return array_pop($result);
	}

	/**
	 * Fix mismatched player name for a given XUID.
	 *
	 * Optionally registers the XUID if `$register` is true.
	 * Updates any records where the XUID is associated with a different player name
	 * and returns the update result.
	 *
	 * @param DbManager $db Database manager instance.
	 * @param string $xuid XUID to verify.
	 * @param string $name Correct player name to associate.
	 * @param bool $register Whether to register the account if not exists.
	 * @return UpdateResult Returns `SUCCESS` if updated, `NO_CHANGE` if already correct.
	 */
	public static function fixXuidNameAssociation(DbManager $db, string $xuid, string $name, bool $register = false) : UpdateResult {
		return $db->transaction(static function() use ($register, $name, $xuid, $db) : UpdateResult {
			if ($register) {
				self::register($db, $xuid, $name);
			}
			$eq = $db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
				->where(SchemaConstants::COL_XUID, $xuid)
				->where(SchemaConstants::COL_PLAYER_NAME, '<>', $name)
				->column(SchemaConstants::COL_PLAYER_NAME);
			if (count($eq) !== 0) {
				$r = $db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
					->where(SchemaConstants::COL_XUID, $xuid)
					->update([
						SchemaConstants::COL_PLAYER_NAME => $name,
						SchemaConstants::COL_LAST_MODIFIED_TIME => time(),
					]);
				return UpdateResult::fromRow($r);
			}
			return UpdateResult::NO_CHANGE;
		});
	}
}