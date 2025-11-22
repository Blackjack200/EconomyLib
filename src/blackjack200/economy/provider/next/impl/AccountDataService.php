<?php
declare(strict_types=1);

namespace blackjack200\economy\provider\next\impl;

use blackjack200\economy\provider\next\AccountDataProxy;
use blackjack200\economy\provider\next\impl\tools\BidirectionalIndexedDataVisitor;
use blackjack200\economy\provider\next\impl\types\IdentifierProvider;
use blackjack200\economy\provider\next\impl\types\SchemaConstants;
use blackjack200\economy\provider\UpdateResult;
use Closure;
use InvalidArgumentException;
use JsonException;
use stdClass;
use think\db\Raw;
use think\DbManager;

/**
 * @internal
 * @see AccountDataProxy
 */
class AccountDataService {
	/**
	 * Retrieve all account data for a given identifier.
	 *
	 * This method fetches the full dataset associated with the provided
	 * `IdentifierProvider`. The provider resolves the user ID and executes
	 * a database query through `internalGetData`.
	 *
	 * @param DbManager $db Database manager instance.
	 * @param IdentifierProvider $id Identifier resolver for the account.
	 * @return array|null Returns the data array if present, otherwise null.
	 */
	public static function getAll(DbManager $db, IdentifierProvider $id) : ?array {
		return $id($db, static fn(int $uid) => self::internalGetData($db, $uid));
	}

	/**
	 * Replace all account data for a given identifier.
	 *
	 * The `$raw` value must be either an array or an `stdClass` instance.
	 * The data is JSON-encoded and stored in the database. If invalid input
	 * is provided, an `InvalidArgumentException` is thrown.
	 *
	 * @param DbManager $db Database manager instance.
	 * @param IdentifierProvider $id Identifier resolver for the account.
	 * @param mixed $raw New account data (array or stdClass).
	 * @return UpdateResult Returns an `UpdateResult` indicating success, no change, or internal error.
	 * @throws InvalidArgumentException When `$raw` is neither array nor stdClass.
	 * @throws JsonException When `json_encode($raw)` failed
	 */
	public static function setAll(DbManager $db, IdentifierProvider $id, $raw) : UpdateResult {
		if (!is_array($raw) || !($raw instanceof stdClass)) {
			throw new InvalidArgumentException("invalid argument raw: " . var_export($raw, true));
		}
		$encoded = json_encode($raw, JSON_THROW_ON_ERROR);
		return $id($db, static function(int $uid) use ($db, $encoded) : UpdateResult {
			return self::setAllInternal($db, $uid, $encoded);
		}, UpdateResult::INTERNAL_ERROR);
	}

	/**
	 * Set a single key/value pair for an account.
	 *
	 * The value must be JSON-serializable. The operation is atomic and
	 * updates the database directly.
	 *
	 * @param DbManager $db Database manager instance.
	 * @param IdentifierProvider $id Identifier resolver for the account.
	 * @param string $key Key to set.
	 * @param mixed $value Value to store (JSON-serializable).
	 * @return UpdateResult Returns an `UpdateResult` indicating success, no change, or internal error.
	 * @throws JsonException When `json_encode($raw)` failed
	 */
	public static function set(DbManager $db, IdentifierProvider $id, string $key, $value) : UpdateResult {
		$encoded = json_encode($value, JSON_THROW_ON_ERROR);
		return $id($db, static fn(int $uid) => self::setKeyInner($db, $uid, $key, $encoded),
			UpdateResult::INTERNAL_ERROR);
	}

	/**
	 * Atomically update a key using a provided operator function.
	 *
	 * The operator receives the old value (or null if absent) and returns
	 * the new value. The result is JSON-encoded and stored. Guarantees atomicity.
	 *
	 * @template T
	 * @param DbManager $db Database manager instance.
	 * @param IdentifierProvider $id Identifier resolver for the account.
	 * @param string $key Key to update.
	 * @param Closure(T|null):(T|null) $operator Function that receives old value and returns new value.
	 * @return UpdateResult Returns an `UpdateResult` indicating success, no change, or internal error.
	 */
	public static function update(DbManager $db, IdentifierProvider $id, string $key, Closure $operator) : UpdateResult {
		return $id($db, static function(int $uid) use ($operator, $key, $db) : UpdateResult {
			$old = self::internalGetData($db, $uid) ?? [];
			$encoded = json_encode($operator($old[$key] ?? null), JSON_THROW_ON_ERROR);
			return self::setKeyInner($db, $uid, $key, $encoded);
		}, UpdateResult::INTERNAL_ERROR);
	}

	/**
	 * Increment or decrement a numeric value for a key.
	 *
	 * Supports signed or unsigned operations. If `$delta` is zero, returns NO_CHANGE.
	 *
	 * @param DbManager $db Database manager instance.
	 * @param IdentifierProvider $id Identifier resolver for the account.
	 * @param string $key Key to modify.
	 * @param int $delta Amount to increment/decrement.
	 * @param bool $signed Whether negative values are allowed.
	 * @return UpdateResult Returns an `UpdateResult` indicating success, no change, or internal error.
	 */
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

	/**
	 * Delete a key from the account data.
	 *
	 * Removes the key from the JSON store atomically.
	 *
	 * @param DbManager $db Database manager instance.
	 * @param IdentifierProvider $id Identifier resolver for the account.
	 * @param string $key Key to delete.
	 * @return UpdateResult Returns an `UpdateResult` indicating success, no change, or internal error.
	 */
	public static function delete(DbManager $db, IdentifierProvider $id, string $key) : UpdateResult {
		return $id($db, static function(int $uid) use ($key, $db) : UpdateResult {
			return self::deleteKeyInner($db, $uid, $key);
		}, UpdateResult::INTERNAL_ERROR);
	}

	/**
	 * Apply a operator to update all account data.
	 *
	 * The operator receives the full old dataset and returns a new dataset.
	 * Ensures atomic replacement of all account data.
	 *
	 * @param DbManager $db Database manager instance.
	 * @param IdentifierProvider $id Identifier resolver for the account.
	 * @param Closure(array):array $operator Function transforming the full dataset.
	 * @return bool True if successful, false otherwise.
	 */
	public static function updateAll(DbManager $db, IdentifierProvider $id, Closure $operator) : bool {
		return $id($db, static function(int $uid) use ($operator, $db) : UpdateResult {
			$old = self::internalGetData($db, $uid) ?? [];

			$newVal = $operator($old);
			$encoded = json_encode($newVal, JSON_THROW_ON_ERROR);
			return self::setAllInternal($db, $uid, $encoded);
		}, UpdateResult::INTERNAL_ERROR);
	}

	/**
	 * Retrieve a sorted view of account data based on a numeric key.
	 *
	 * Returns a `BidirectionalIndexedDataVisitor` for bidirectional iteration
	 * over the top `$n` entries ordered ascending or descending.
	 *
	 * @param DbManager $db Database manager instance.
	 * @param string $key Key used for sorting.
	 * @param int $n Maximum number of results.
	 * @param bool $asc Sort ascending if true, descending if false.
	 * @return BidirectionalIndexedDataVisitor Sorted view over account data.
	 */
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