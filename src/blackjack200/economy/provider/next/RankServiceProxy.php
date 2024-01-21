<?php

namespace blackjack200\economy\provider\next;

use blackjack200\economy\EconomyLoader;
use blackjack200\economy\provider\next\impl\RankService;
use blackjack200\economy\provider\next\impl\types\IdentifierProvider;
use Generator;
use libasync\await\Await;
use think\DbManager;

/**
 * @method static Generator|string[] getRegistered()
 * @method static Generator|bool register(string $basename, string $display)
 * @method static Generator|bool set(string $basename, string $newDisplay)
 * @method static Generator|bool unregister(string $basename)
 * @method static Generator|bool addRankToPlayer(IdentifierProvider $id, string $rankBasename, int $deadline)
 * @method static Generator|bool removeRankFromPlayer(IdentifierProvider $id, string $rankBasename)
 * @method static Generator|array<string, int> getRanksFromPlayer(IdentifierProvider $id)
 */
class RankServiceProxy {
	public static function __callStatic(string $name, array $arguments) {
		return yield from Await::threadify(static fn(DbManager $db) => RankService::$name($db, ...$arguments), EconomyLoader::getInstance()->getExecutor());
	}
}