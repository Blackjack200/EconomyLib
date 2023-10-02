<?php

namespace blackjack200\economy\provider\next;

use blackjack200\economy\EconomyLoader;
use blackjack200\economy\provider\next\impl\AccountMetadataService;
use blackjack200\economy\provider\next\impl\tools\SortedDataVisitor;
use blackjack200\economy\provider\next\impl\types\IdentifierProvider;
use Closure;
use libasync\await\Await;
use think\DbManager;

/**
 * @method static array|null getAll(IdentifierProvider $id)
 * @method static void setAll(IdentifierProvider $id, $raw)
 * @method static void register(IdentifierProvider $id)
 * @method static void set(IdentifierProvider $id, string $type, string $key, $value)
 * @method static void update(IdentifierProvider $id, string $type, string $key, Closure $operator)
 * @method static void updateAll(IdentifierProvider $id, Closure $operator)
 * @method static SortedDataVisitor sort(string $key, int $n, bool $asc)
 */
class AccountDataProxy {
	public static function __callStatic(string $name, array $arguments) {
		return Await::async(static fn(DbManager $db) => AccountMetadataService::$name($db, ...$arguments), EconomyLoader::getInstance()->getXyronExecutor());
	}
}