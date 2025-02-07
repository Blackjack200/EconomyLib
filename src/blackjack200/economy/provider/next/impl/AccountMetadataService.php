<?php

namespace blackjack200\economy\provider\next\impl;

use blackjack200\economy\provider\next\impl\types\IdentifierProvider;
use blackjack200\economy\provider\next\impl\types\SchemaConstants;
use blackjack200\economy\provider\UpdateResult;
use think\DbManager;

class AccountMetadataService {
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

	public static function delete(DbManager $db, IdentifierProvider $id) : UpdateResult {
		return $id($db, static fn(int $uid) => UpdateResult::fromRow($db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
			->where(SchemaConstants::COL_UID, $uid)
			->delete()
		), UpdateResult::INTERNAL_ERROR);
	}

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

	public static function getUid(DbManager $db, ?string $xuid, string $name) : ?int {
		$result = $db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
			->where(SchemaConstants::COL_XUID, $xuid)
			->where(SchemaConstants::COL_PLAYER_NAME, $name)
			->order(SchemaConstants::COL_LAST_MODIFIED_TIME, 'desc')
			->column(SchemaConstants::COL_UID);
		return array_pop($result);
	}

	/**
	 * Return whether it has changed in the verification.
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