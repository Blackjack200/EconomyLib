<?php

namespace blackjack200\economy\provider\next;

use blackjack200\economy\EconomyLoader;
use blackjack200\economy\provider\next\impl\AccountMetadataService;
use blackjack200\economy\provider\next\impl\types\IdentifierProvider;
use blackjack200\economy\provider\UpdateResult;
use Generator;
use think\DbManager;

/**
 * @method static Generator|UpdateResult register(string|null $xuid, string $name)
 * @method static Generator|UpdateResult delete(IdentifierProvider $id)
 * @method static Generator|string|null getName(string $xuid)
 * @method static Generator|string|null getXuid(string $name)
 * @method static Generator|int|null getUid(string|null $xuid, string $name)
 * @method static Generator|UpdateResult fixXuidNameAssociation(string $xuid, string $name, bool $register = false)
 **/
class AccountMetadataServiceProxy {
	public static function __callStatic(string $name, array $arguments) {
		return yield from EconomyLoader::db(static fn(DbManager $db) => AccountMetadataService::$name($db, ...$arguments));
	}
}