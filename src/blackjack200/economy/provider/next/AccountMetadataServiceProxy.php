<?php

namespace blackjack200\economy\provider\next;

use blackjack200\economy\EconomyLoader;
use blackjack200\economy\provider\next\impl\AccountMetadataService;
use blackjack200\economy\provider\next\impl\types\IdentifierProvider;
use libasync\await\Await;
use think\DbManager;

/**
 * @method static void register(string $xuid, string $name)
 * @method static bool delete(IdentifierProvider $id)
 * @method static string|null getName(string $xuid)
 * @method static string|null getXuid(string $name)
 * @method static bool fixXuidNameAssociation(string $xuid, string $name)
 * @method static array|null getAccountData(IdentifierProvider $id)
 **/
class AccountMetadataServiceProxy {
	public static function __callStatic(string $name, array $arguments) {
		return Await::async(static fn(DbManager $db) => AccountMetadataService::$name($db, ...$arguments), EconomyLoader::getInstance()->getXyronExecutor());
	}
}