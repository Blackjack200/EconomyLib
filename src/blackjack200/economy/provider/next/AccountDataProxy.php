<?php

namespace blackjack200\economy\provider\next;

use blackjack200\economy\EconomyLoader;
use blackjack200\economy\provider\next\impl\AccountDataService;
use blackjack200\economy\provider\next\impl\tools\BidirectionalIndexedDataVisitor;
use blackjack200\economy\provider\next\impl\types\IdentifierProvider;
use blackjack200\economy\provider\UpdateResult;
use Closure;
use Generator;
use think\DbManager;

/**
 * @method static Generator|array|null getAll(IdentifierProvider $id)
 * @method static Generator|UpdateResult setAll(IdentifierProvider $id, $raw)
 * @method static Generator|UpdateResult set(IdentifierProvider $id, string $key, $value)
 * @method static Generator|UpdateResult update(IdentifierProvider $id, string $key, Closure $operator)
 * @method static Generator|UpdateResult updateAll(IdentifierProvider $id, Closure $operator)
 * @method static Generator|UpdateResult numericDelta(IdentifierProvider $id, string $key, int $delta, bool $signed = true)
 * @method static Generator|UpdateResult delete(IdentifierProvider $id, string $key)
 * @method static Generator|BidirectionalIndexedDataVisitor sort(string $key, int $n, bool $asc)
 */
class AccountDataProxy {
	public static function __callStatic(string $name, array $arguments) {
		return yield from EconomyLoader::db(static fn(DbManager $db) : mixed => AccountDataService::$name($db, ...$arguments));
	}

	public static function formatKey(string $key) : string {
		return str_replace('.', '_', $key);
	}
}