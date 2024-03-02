<?php

namespace blackjack200\economy\provider\next\impl;

use blackjack200\economy\provider\next\impl\tools\BidirectionalIndexedDataVisitor;
use blackjack200\economy\provider\next\impl\types\IdentifierProvider;
use blackjack200\economy\provider\next\impl\types\SchemaConstants;
use think\DbManager;

class AccountDataService {
	private static function getPdoType($data) : ?string {
		if (is_string($data)) {
			return 'string';
		}
		if (is_float($data)) {
			return 'float';
		}
		if (is_int($data)) {
			return 'int';
		}
		if (is_bool($data)) {
			return 'bool';
		}
		return null;
	}

	public static function getAll(DbManager $db, IdentifierProvider $id) : ?array {
		return $id($db, static fn(string $xuid) => self::internalGetData($db, $xuid));
	}

	public static function setAll(DbManager $db, IdentifierProvider $id, $raw) : bool {
		$d = json_encode($raw, JSON_THROW_ON_ERROR);
		return $id($db, static function(string $xuid) use ($d, $db) : bool {
			$data = [];
			$data[SchemaConstants::COL_DATA] = json_decode($d, true, 512, JSON_THROW_ON_ERROR);
			return $db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
				->json([SchemaConstants::COL_DATA], true)
				->where(SchemaConstants::COL_XUID, $xuid)
				->update($data);
		}, false);
	}

	public static function set(DbManager $db, IdentifierProvider $id, string $key, $value) : bool {
		$data = [];
		$data[SchemaConstants::COL_DATA . "->$key"] = $value;
		return $id($db, static fn(string $xuid) => $db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
			->json([SchemaConstants::COL_DATA], true)
			->where(SchemaConstants::COL_XUID, $xuid)
			->update($data)
			, false);
	}

	public static function update(DbManager $db, IdentifierProvider $id, string $key, \Closure $operator) : bool {
		return $id($db, static function(string $xuid) use ($operator, $key, $db) : bool {
			$old = self::internalGetData($db, $xuid) ?? [];

			$data = [];
			$data[SchemaConstants::COL_DATA . "->$key"] = $operator($old[$key] ?? null);

			return $db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
				->json([SchemaConstants::COL_DATA], true)
				->where(SchemaConstants::COL_XUID, $xuid)
				->update($data);
		}, false);
	}

	public static function delete(DbManager $db, IdentifierProvider $id, string $key) : bool {
		return $id($db, static function(string $xuid) use ($key, $db) : bool {
			$old = self::internalGetData($db, $xuid) ?? [];
			unset($old[$key]);
			$data = [];
			$data[SchemaConstants::COL_DATA] = $old;
			return $db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
				->json([SchemaConstants::COL_DATA], true)
				->where(SchemaConstants::COL_XUID, $xuid)
				->update($data);
		}, false);
	}

	public static function updateAuto(DbManager $db, IdentifierProvider $id, string $key, \Closure $operator) : bool {
		return $id($db, static function(string $xuid) use ($operator, $key, $db) : bool {
			$old = self::internalGetData($db, $xuid) ?? [];

			$data = [];
			$userNewData = $operator($old[$key] ?? null);
			$data[SchemaConstants::COL_DATA . "->$key"] = $userNewData;

			return $db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
				->json([SchemaConstants::COL_DATA], true)
				->where(SchemaConstants::COL_XUID, $xuid)
				->update($data);
		}, false);
	}

	public static function updateAll(DbManager $db, IdentifierProvider $id, \Closure $operator) : bool {
		return $id($db, static function(string $xuid) use ($operator, $db) : bool {
			$old = self::internalGetData($db, $xuid) ?? [];

			$data = [];
			$data[SchemaConstants::COL_DATA] = $operator($old);

			return $db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
				->json([SchemaConstants::COL_DATA], true)
				->where(SchemaConstants::COL_XUID, $xuid)
				->update($data);
		}, false);
	}

	public static function sort(DbManager $db, string $key, int $n, bool $asc) : BidirectionalIndexedDataVisitor {
		$mode = $asc ? 'ASC' : 'DESC';
		//TODO avoid inject
		$sorted = $db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
			->json([SchemaConstants::COL_DATA], true)
			->orderRaw(SchemaConstants::COL_DATA . "->'$.$key' $mode")
			->limit($n)
			->select()->toArray();
		return BidirectionalIndexedDataVisitor::create($key, $sorted);
	}

	private static function internalGetData(DbManager $db, string $xuid) : ?array {
		$result = $db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
			->cache($xuid)
			->json([SchemaConstants::COL_DATA], true)
			->where(SchemaConstants::COL_XUID, $xuid)
			->find();
		return $result[SchemaConstants::COL_DATA] ?? null;
	}
}