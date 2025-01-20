<?php
declare(strict_types=1);

namespace blackjack200\economy\provider\next\impl;

use blackjack200\economy\provider\next\impl\tools\BidirectionalIndexedDataVisitor;
use blackjack200\economy\provider\next\impl\types\IdentifierProvider;
use blackjack200\economy\provider\next\impl\types\SchemaConstants;
use Closure;
use InvalidArgumentException;
use pocketmine\utils\Utils;
use stdClass;
use think\db\Raw;
use think\DbManager;

class AccountDataService {
	public static function getAll(DbManager $db, IdentifierProvider $id) : ?array {
		return $id($db, static fn(int $uid) => self::internalGetData($db, $uid));
	}

	public static function setAll(DbManager $db, IdentifierProvider $id, $raw) : bool {
		if (!is_array($raw) || !($raw instanceof stdClass)) {
			throw new InvalidArgumentException("invalid argument raw: " . var_export($raw, true));
		}
		$encoded = json_encode($raw, JSON_THROW_ON_ERROR);
		return $id($db, static function(int $uid) use ($db, $encoded) : bool {
			return self::setAllInternal($db, $uid, $encoded);
		}, false);
	}

	public static function set(DbManager $db, IdentifierProvider $id, string $key, $value) : bool {
		$encoded = json_encode($value, JSON_THROW_ON_ERROR);
		Utils::assumeNotFalse($encoded);
		return $id($db, static function(int $uid) use ($encoded, $key, $db) {
			return self::setKeyInner($db, $uid, $key, $encoded);
		}, false);
	}

	/**
	 * @template T
	 * @param Closure(T|null):(T|null) $operator
	 */
	public static function update(DbManager $db, IdentifierProvider $id, string $key, Closure $operator) : bool {
		return $id($db, static function(int $uid) use ($operator, $key, $db) : bool {
			$old = self::internalGetData($db, $uid) ?? [];
			$encoded = json_encode($operator($old[$key] ?? null), JSON_THROW_ON_ERROR);
			return self::setKeyInner($db, $uid, $key, $encoded);
		}, false);
	}

	public static function numericDelta(DbManager $db, IdentifierProvider $id, string $key, int $delta, bool $signed = true) : bool {
		if ($delta === 0) {
			return true;
		}
		return $id($db, static function(int $uid) use ($signed, $delta, $key, $db) : bool {
			$path = "'" . AccountDataHelper::jsonKeyPath($key) . "'";
			$delta = ($delta > 0) ? "+ $delta" : "$delta";
			$sign = $signed ? 'signed' : 'unsigned';
			$f = "json_set(data, $path, cast((coalesce(json_extract(data, $path), 0)  $delta) as $sign))";
			$ret = $db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
				->json([SchemaConstants::COL_DATA], true)
				->where(SchemaConstants::COL_UID, $uid)
				->limit(1);
			if (!$signed) {
				$ret->whereRaw("cast((coalesce(json_extract(data, $path), 0)  $delta) as signed) >= 0");
			}
			$ret = $ret->update([SchemaConstants::COL_DATA => new Raw($f)]);
			return $ret === 1;
		}, false);
	}

	public static function delete(DbManager $db, IdentifierProvider $id, string $key) : bool {
		return $id($db, static function(int $uid) use ($key, $db) : bool {
			return self::deleteKeyInner($db, $uid, $key);
		}, false);
	}

	public static function updateAll(DbManager $db, IdentifierProvider $id, Closure $operator) : bool {
		return $id($db, static function(int $uid) use ($operator, $db) : bool {
			$old = self::internalGetData($db, $uid) ?? [];

			$newVal = $operator($old);
			$encoded = json_encode($newVal, JSON_THROW_ON_ERROR);
			return self::setAllInternal($db, $uid, $encoded);
		}, false);
	}

	public static function sort(DbManager $db, string $key, int $n, bool $asc) : BidirectionalIndexedDataVisitor {
		$path = AccountDataHelper::jsonKeyPath($key);
		$mode = $asc ? 'ASC' : 'DESC';
		//TODO avoid inject
		$sorted = $db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
			->json([SchemaConstants::COL_DATA], true)
			->orderRaw("cast(coalesce(json_extract(data, '$path'), 0) as signed) $mode")
			->limit($n)
			->select()->toArray();
		return BidirectionalIndexedDataVisitor::create($key, $sorted);
	}

	private static function internalGetData(DbManager $db, int $uid) : ?array {
		$result = $db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
			->cache(false)
			->json([SchemaConstants::COL_DATA], true)
			->where(SchemaConstants::COL_UID, $uid)
			->find();
		return $result[SchemaConstants::COL_DATA] ?? null;
	}

	private static function setKeyInner(DbManager $db, int $uid, string $key, string $encoded) : bool {
		$path = AccountDataHelper::jsonKeyPath($key);
		$encoded = base64_encode($encoded);
		$f = "json_set(data, '$path', json_pretty(from_base64('$encoded')))";
		$ret = $db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
			->json([SchemaConstants::COL_DATA], true)
			->where(SchemaConstants::COL_UID, $uid)
			->limit(1)
			->update([SchemaConstants::COL_DATA => new Raw($f)]);
		return $ret === 1;
	}

	private static function deleteKeyInner(DbManager $db, int $uid, string $key) : bool {
		$path = AccountDataHelper::jsonKeyPath($key);
		$f = "json_remove(data, '$path')";
		$ret = $db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
			->json([SchemaConstants::COL_DATA], true)
			->where(SchemaConstants::COL_UID, $uid)
			->limit(1)
			->update([SchemaConstants::COL_DATA => new Raw($f)]);
		return $ret === 1;
	}

	private static function setAllInternal(DbManager $db, int $uid, string $encoded) : bool {
		$encoded = base64_encode($encoded);
		return $db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
				->json([SchemaConstants::COL_DATA], true)
				->where(SchemaConstants::COL_UID, $uid)
				->update([
					SchemaConstants::COL_DATA => new Raw("json_pretty(from_base64('$encoded'))"),
				]) === 1;
	}
}