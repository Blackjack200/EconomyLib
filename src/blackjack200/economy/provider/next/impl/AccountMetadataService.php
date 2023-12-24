<?php

namespace blackjack200\economy\provider\next\impl;

use blackjack200\economy\provider\next\impl\types\IdentifierProvider;
use blackjack200\economy\provider\next\impl\types\SchemaConstants;
use LogicException;
use think\DbManager;

class AccountMetadataService {
	public static function register(DbManager $db, string $xuid, string $name) : void {
		$db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
			->extra('IGNORE')
			->insert([
				SchemaConstants::COL_XUID => $xuid,
				SchemaConstants::COL_LAST_MODIFIED_TIME => time(),
				SchemaConstants::COL_DATA => '{"_":"_"}',
				SchemaConstants::COL_PLAYER_NAME => $name,
			]);
	}

	public static function delete(DbManager $db, IdentifierProvider $id) : bool {
		return $id($db, static fn(string $xuid) => $db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
			->where(SchemaConstants::COL_XUID, $xuid)
			->delete()
			, false);
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

	/**
	 * Return whether it has changed in the verification.
	 */
	public static function fixXuidNameAssociation(DbManager $db, string $xuid, string $name) : bool {
		return $db->transaction(static function() use ($name, $xuid, $db) : bool {
			self::register($db, $xuid, $name);
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
				if ($r === 1) {
					return true;
				}
				throw new LogicException("Something wrong has happened.");
			}
			return false;
		});
	}
}