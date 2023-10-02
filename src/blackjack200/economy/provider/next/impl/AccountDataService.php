<?php

namespace blackjack200\economy\provider\next\impl;

use blackjack200\economy\provider\next\impl\tools\SortedDataVisitor;
use blackjack200\economy\provider\next\impl\types\IdentifierProvider;
use blackjack200\economy\provider\next\impl\types\SchemaConstants;
use think\DbManager;

class AccountDataService {
	public static function getAll(DbManager $db, IdentifierProvider $id) : ?array {
		return $id($db, static fn(string $xuid) => self::internalGetData($db, $xuid));
	}

	public static function setAll(DbManager $db, IdentifierProvider $id, $raw) : void {
		$d = json_encode($raw, JSON_THROW_ON_ERROR);
		$id($db, static function(string $xuid) use ($d, $db) : void {
			$data = [];
			$data[SchemaConstants::COL_DATA] = json_decode($d, true, 512, JSON_THROW_ON_ERROR);
			$db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
				->json([SchemaConstants::COL_DATA], true)
				->where(SchemaConstants::COL_XUID, $xuid)
				->update($data);
		});
	}

	public static function register(DbManager $db, IdentifierProvider $id) : void {
		$id($db, static fn(string $xuid) => $db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
			->extra('IGNORE')
			->insert([
				SchemaConstants::COL_XUID => $xuid,
				SchemaConstants::COL_DATA => '{}',
			])
		);
	}

	public static function set(DbManager $db, IdentifierProvider $id, string $type, string $key, $value) : void {
		$data = [];
		$data[SchemaConstants::COL_DATA . "->$key"] = $value;
		$id($db, static fn(string $xuid) => $db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
			->json([SchemaConstants::COL_DATA], true)
			->setFieldType([SchemaConstants::COL_DATA . "->$key" => $type])
			->where(SchemaConstants::COL_XUID, $xuid)
			->update($data)
		);
	}

	public static function update(DbManager $db, IdentifierProvider $id, string $type, string $key, \Closure $operator) : void {
		$id($db, static function(string $xuid) use ($type, $operator, $key, $db) : void {
			$old = self::internalGetData($db, $xuid, [SchemaConstants::COL_DATA . "->$key" => $type]) ?? [];

			$data = [];
			$data[SchemaConstants::COL_DATA . "->$key"] = $operator($old[$key] ?? null);

			$db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
				->json([SchemaConstants::COL_DATA], true)
				->setFieldType([SchemaConstants::COL_DATA . "->$key" => $type])
				->where(SchemaConstants::COL_XUID, $xuid)
				->update($data);
		});
	}

	public static function updateAll(DbManager $db, IdentifierProvider $id, \Closure $operator) : void {
		$id($db, static function(string $xuid) use ($operator, $db) : void {
			$old = self::internalGetData($db, $xuid) ?? [];

			$data = [];
			$data[SchemaConstants::COL_DATA] = $operator($old);

			$db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
				->json([SchemaConstants::COL_DATA], true)
				->where(SchemaConstants::COL_XUID, $xuid)
				->update($data);
		});
	}

	public static function sort(DbManager $db, string $key, int $n, bool $asc) : SortedDataVisitor {
		$mode = $asc ? 'ASC' : 'DESC';
		//TODO avoid inject
		$sorted = $db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
			->json([SchemaConstants::COL_DATA], true)
			->orderRaw(SchemaConstants::COL_DATA . "->'$.$key' $mode")
			->limit($n)
			->select()->toArray();
		return SortedDataVisitor::create($key, $sorted);
	}

	private static function internalGetData(DbManager $db, string $xuid, array $fieldType = []) : ?array {
		$result = $db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
			->json([SchemaConstants::COL_DATA], true)
			->setFieldType($fieldType)
			->where(SchemaConstants::COL_XUID, $xuid)
			->find();
		return $result[SchemaConstants::COL_DATA] ?? null;
	}
}