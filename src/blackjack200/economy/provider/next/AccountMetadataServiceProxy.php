<?php

namespace blackjack200\economy\provider\next;

use blackjack200\economy\EconomyLoader;
use blackjack200\economy\provider\next\impl\AccountMetadataService;
use blackjack200\economy\provider\next\impl\types\IdentifierProvider;
use Generator;
use think\DbManager;

/**
 * @method static Generator|void register(string $xuid, string $name)
 * @method static Generator|bool delete(IdentifierProvider $id)
 * @method static Generator|string|null getName(string $xuid)
 * @method static Generator|string|null getXuid(string $name)
 * @method static Generator|bool fixXuidNameAssociation(string $xuid, string $name)
 * @method static Generator|array|null getAccountData(IdentifierProvider $id)
 **/
class AccountMetadataServiceProxy {
	public static function __callStatic(string $name, array $arguments) {
		return yield from EconomyLoader::db(static fn(DbManager $db) => AccountMetadataService::$name($db, ...$arguments));
	}
}