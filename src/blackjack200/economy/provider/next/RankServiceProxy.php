<?php

namespace blackjack200\economy\provider\next;

use blackjack200\economy\EconomyLoader;
use blackjack200\economy\provider\next\impl\RankService;
use blackjack200\economy\provider\next\impl\types\IdentifierProvider;
use DateTime;
use libasync\await\Await;
use think\DbManager;

/**
 * @method static string[] getRegistered()
 * @method static void register(string $basename, string $display)
 * @method static bool set(string $basename, string $newDisplay)
 * @method static bool unregister(string $basename)
 * @method static bool addRankToPlayer(IdentifierProvider $id, string $rankBasename, DateTime|int $deadline)
 * @method static bool removeRankFromPlayer(IdentifierProvider $id, string $rankBasename)
 * @method static array<string, DateTime> getRanksFromPlayer(IdentifierProvider $id)
 */
class RankServiceProxy {
	public static function __callStatic(string $name, array $arguments) {
		return Await::async(static fn(DbManager $db) => RankService::$name($db, ...$arguments), EconomyLoader::getInstance()->getXyronExecutor());
	}
}