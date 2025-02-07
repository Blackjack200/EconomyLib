<?php

namespace blackjack200\economy\provider\next;

use blackjack200\economy\EconomyLoader;
use blackjack200\economy\provider\next\impl\RankService;
use blackjack200\economy\provider\next\impl\types\IdentifierProvider;
use blackjack200\economy\provider\UpdateResult;
use Generator;
use think\DbManager;

/**
 * @method static Generator|string[] getRegistered()
 * @method static Generator|UpdateResult register(string $basename, string $display)
 * @method static Generator|UpdateResult set(string $basename, string $newDisplay)
 * @method static Generator|UpdateResult unregister(string $basename)
 * @method static Generator|UpdateResult addRankToPlayer(IdentifierProvider $id, string $rankBasename, int $deadline)
 * @method static Generator|UpdateResult removeRankFromPlayer(IdentifierProvider $id, string $rankBasename)
 * @method static Generator|array<string, int> getRanksFromPlayer(IdentifierProvider $id)
 */
class RankServiceProxy {
	public static function __callStatic(string $name, array $arguments) {
		return yield from EconomyLoader::db(static fn(DbManager $db) => RankService::$name($db, ...$arguments));
	}
}