<?php

namespace blackjack200\economy\provider\next\impl;

use blackjack200\economy\provider\next\impl\types\IdentifierProvider;
use blackjack200\economy\provider\next\impl\types\SchemaConstants;
use LogicException;
use think\DbManager;

class AccountService {
	public static function register(DbManager $db, string $xuid, string $name) : void {
		$db->transaction(static function() use ($name, $xuid, $db) : void {
			$db->table(SchemaConstants::TABLE_XUID)
				->extra('IGNORE')
				->insert([
					SchemaConstants::COL_XUID => $xuid,
					SchemaConstants::COL_PLAYER_NAME => $name,
				]);
			$db->table(SchemaConstants::TABLE_ACCOUNT)
				->extra('IGNORE')
				->insert([SchemaConstants::COL_XUID => $xuid]);
		});
	}

	public static function delete(DbManager $db, IdentifierProvider $id) : void {
		$id($db, static fn(string $xuid) => $db->table(SchemaConstants::TABLE_XUID)
			->where(SchemaConstants::COL_XUID, $xuid)
			->delete()
		);
	}

	public static function getName(DbManager $db, string $xuid) : ?string {
		$result = $db->table(SchemaConstants::TABLE_XUID)
			->where(SchemaConstants::COL_XUID, $xuid)
			->column(SchemaConstants::COL_PLAYER_NAME);
		if (count($result) > 1) {
			throw new LogicException("Xuid: $xuid should not be associated with " . var_export($result, true));
		}
		return array_pop($result);
	}

	public static function getXuid(DbManager $db, string $name) : ?string {
		$result = $db->table(SchemaConstants::TABLE_XUID)
			->where(SchemaConstants::COL_PLAYER_NAME, $name)
			->column(SchemaConstants::COL_XUID);
		if (count($result) > 1) {
			throw new LogicException("Name: $name should not be associated with " . var_export($result, true));
		}
		return array_pop($result);
	}

	/**
	 * Return whether it has changed in the verification.
	 */
	public static function fixXuidNameAssociation(DbManager $db, string $xuid, string $name) : bool {
		return $db->transaction(static function() use ($name, $xuid, $db) : bool {
			$eq = $db->table(SchemaConstants::TABLE_XUID)
				->where(SchemaConstants::COL_XUID, $xuid)
				->where(SchemaConstants::COL_PLAYER_NAME, $name)
				->column(SchemaConstants::COL_PLAYER_NAME);
			if (count($eq) > 1) {
				throw new LogicException("Xuid: $xuid should not be associated with " . var_export($eq, true));
			}
			if (count($eq) !== 1) {
				$oldName = array_pop($eq);
				assert($oldName !== null);
				$r = $db->table(SchemaConstants::TABLE_XUID)
					->where(SchemaConstants::COL_XUID, $xuid)
					->update([SchemaConstants::COL_PLAYER_NAME => $name]);
				if ($r === 1) {
					return true;
				}
				throw new LogicException("Something wrong has happened.");
			}
			return false;
		});
	}

	public static function getAccountData(DbManager $db, IdentifierProvider $id) : ?array {
		return $id($db, static fn(string $xuid) => $db->table(SchemaConstants::TABLE_ACCOUNT)
			->where(SchemaConstants::COL_XUID, $xuid)->find()
		);
	}

	public static function setAccountData(DbManager $db, IdentifierProvider $id, array $data) : void {
		$id($db, static fn(string $xuid) => $db->table(SchemaConstants::TABLE_ACCOUNT)
			->where(SchemaConstants::COL_XUID, $xuid)
			->update($data)
		);
	}
}