<?php

namespace blackjack200\economy\provider\next;

use blackjack200\economy\EconomyLoader;
use blackjack200\economy\provider\next\impl\AccountDataService;
use blackjack200\economy\provider\next\impl\tools\BidirectionalIndexedDataVisitor;
use blackjack200\economy\provider\next\impl\types\IdentifierProvider;
use Closure;
use libasync\await\Await;
use think\DbManager;

/**
 * @method static array|null getAll(IdentifierProvider $id)
 * @method static bool setAll(IdentifierProvider $id, $raw)
 * @method static bool register(IdentifierProvider $id)
 * @method static bool set(IdentifierProvider $id, string $type, string $key, $value)
 * @method static bool setAuto(IdentifierProvider $id, string $key, $value)
 * @method static bool update(IdentifierProvider $id, string $type, string $key, Closure $operator)
 * @method static bool updateAuto(IdentifierProvider $id, string $key, Closure $operator)
 * @method static bool updateAll(IdentifierProvider $id, Closure $operator)
 * @method static bool delete(IdentifierProvider $id, string $key)
 * @method static BidirectionalIndexedDataVisitor sort(string $key, int $n, bool $asc)
 */
class AccountDataProxy {
	public static function __callStatic(string $name, array $arguments) {
		return Await::async(static fn(DbManager $db) => AccountDataService::$name($db, ...$arguments), EconomyLoader::getInstance()->getExecutor());
	}

	public static function formatKey(string $key) : string {
		return str_replace('.', '_', $key);
	}
}