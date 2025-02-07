<?php
declare(strict_types=1);

namespace blackjack200\economy\provider\next\impl;

use blackjack200\economy\provider\next\impl\tools\BidirectionalIndexedDataVisitor;
use blackjack200\economy\provider\next\impl\types\IdentifierProvider;
use blackjack200\economy\provider\next\impl\types\SchemaConstants;
use blackjack200\economy\provider\UpdateResult;
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

	public static function setAll(DbManager $db, IdentifierProvider $id, $raw) : UpdateResult {
		if (!is_array($raw) || !($raw instanceof stdClass)) {
			throw new InvalidArgumentException("invalid argument raw: " . var_export($raw, true));
		}
		$encoded = json_encode($raw, JSON_THROW_ON_ERROR);
		return $id($db, static function(int $uid) use ($db, $encoded) : UpdateResult {
			return self::setAllInternal($db, $uid, $encoded);
		}, UpdateResult::INTERNAL_ERROR);
	}

	public static function set(DbManager $db, IdentifierProvider $id, string $key, $value) : UpdateResult {
		$encoded = json_encode($value, JSON_THROW_ON_ERROR);
		Utils::assumeNotFalse($encoded);
		return $id($db, static fn(int $uid) => self::setKeyInner($db, $uid, $key, $encoded),
			UpdateResult::INTERNAL_ERROR);
	}

	/**
	 * @template T
	 * @param Closure(T|null):(T|null) $operator
	 */
	public static function update(DbManager $db, IdentifierProvider $id, string $key, Closure $operator) : UpdateResult {
		return $id($db, static function(int $uid) use ($operator, $key, $db) : UpdateResult {
			$old = self::internalGetData($db, $uid) ?? [];
			$encoded = json_encode($operator($old[$key] ?? null), JSON_THROW_ON_ERROR);
			return self::setKeyInner($db, $uid, $key, $encoded);
		}, UpdateResult::INTERNAL_ERROR);
	}

	public static function numericDelta(DbManager $db, IdentifierProvider $id, string $key, int $delta, bool $signed = true) : UpdateResult {
		if ($delta === 0) {
			return UpdateResult::NO_CHANGE;
		}
		return $id($db, static function(int $uid) use ($signed, $delta, $key, $db) : UpdateResult {
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
			$affectedRow = $ret->update([SchemaConstants::COL_DATA => new Raw($f)]);
			return match (true) {
				$affectedRow === 1 => UpdateResult::SUCCESS,
				$affectedRow === 0 => UpdateResult::NO_CHANGE,
				default => UpdateResult::INTERNAL_ERROR,
			};
		}, UpdateResult::INTERNAL_ERROR);
	}

	public static function delete(DbManager $db, IdentifierProvider $id, string $key) : UpdateResult {
		return $id($db, static function(int $uid) use ($key, $db) : UpdateResult {
			return self::deleteKeyInner($db, $uid, $key);
		}, UpdateResult::INTERNAL_ERROR);
	}

	public static function updateAll(DbManager $db, IdentifierProvider $id, Closure $operator) : bool {
		return $id($db, static function(int $uid) use ($operator, $db) : UpdateResult {
			$old = self::internalGetData($db, $uid) ?? [];

			$newVal = $operator($old);
			$encoded = json_encode($newVal, JSON_THROW_ON_ERROR);
			return self::setAllInternal($db, $uid, $encoded);
		}, UpdateResult::INTERNAL_ERROR);
	}

	public static function sort(DbManager $db, string $key, int $n, bool $asc) : BidirectionalIndexedDataVisitor {
		$path = base64_encode(AccountDataHelper::jsonKeyPath($key));
		$mode = $asc ? 'ASC' : 'DESC';
		$sorted = $db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
			->json([SchemaConstants::COL_DATA], true)
			->orderRaw("cast(coalesce(json_extract(data, from_base64('$path')), 0) as signed) $mode")
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

	private static function setKeyInner(DbManager $db, int $uid, string $key, string $encoded) : UpdateResult {
		$path = AccountDataHelper::jsonKeyPath($key);
		$encoded = base64_encode($encoded);
		$f = "json_set(data, '$path', json_pretty(from_base64('$encoded')))";
		$affectedRow = $db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
			->json([SchemaConstants::COL_DATA], true)
			->where(SchemaConstants::COL_UID, $uid)
			->limit(1)
			->update([SchemaConstants::COL_DATA => new Raw($f)]);
		return UpdateResult::fromRow($affectedRow);
	}

	private static function deleteKeyInner(DbManager $db, int $uid, string $key) : UpdateResult {
		$path = AccountDataHelper::jsonKeyPath($key);
		$f = "json_remove(data, '$path')";
		$affectedRow = $db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
			->json([SchemaConstants::COL_DATA], true)
			->where(SchemaConstants::COL_UID, $uid)
			->limit(1)
			->update([SchemaConstants::COL_DATA => new Raw($f)]);
		return UpdateResult::fromRow($affectedRow);
	}

	private static function setAllInternal(DbManager $db, int $uid, string $encoded) : UpdateResult {
		$encoded = base64_encode($encoded);
		$affectedRow = $db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
			->json([SchemaConstants::COL_DATA], true)
			->where(SchemaConstants::COL_UID, $uid)
			->update([
				SchemaConstants::COL_DATA => new Raw("json_pretty(from_base64('$encoded'))"),
			]);
		return UpdateResult::fromRow($affectedRow);
	}
}