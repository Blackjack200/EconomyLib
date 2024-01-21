<?php

namespace blackjack200\economy\provider\next;

use blackjack200\economy\EconomyLoader;
use blackjack200\economy\provider\next\impl\AccountDataService;
use blackjack200\economy\provider\next\impl\tools\BidirectionalIndexedDataVisitor;
use blackjack200\economy\provider\next\impl\types\IdentifierProvider;
use Closure;
use Generator;
use libasync\await\Await;
use think\DbManager;

/**
 * @method static Generator|array|null getAll(IdentifierProvider $id)
 * @method static Generator|bool setAll(IdentifierProvider $id, $raw)
 * @method static Generator|bool set(IdentifierProvider $id, string $key, $value)
 * @method static Generator|bool update(IdentifierProvider $id, string $key, Closure $operator)
 * @method static Generator|bool updateAll(IdentifierProvider $id, Closure $operator)
 * @method static Generator|bool delete(IdentifierProvider $id, string $key)
 * @method static Generator|BidirectionalIndexedDataVisitor sort(string $key, int $n, bool $asc)
 */
class AccountDataProxy {
	public static function __callStatic(string $name, array $arguments) {
		return yield from Await::threadify(static fn(DbManager $db) : mixed => AccountDataService::$name($db, ...$arguments), EconomyLoader::getInstance()->getExecutor());
	}

	public static function formatKey(string $key) : string {
		return str_replace('.', '_', $key);
	}
}