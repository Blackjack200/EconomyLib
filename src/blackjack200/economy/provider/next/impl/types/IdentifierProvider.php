<?php

namespace blackjack200\economy\provider\next\impl\types;

use blackjack200\cache\CacheInterface;
use blackjack200\cache\LRUCache;
use Closure;
use pmmp\thread\ThreadSafe;
use pocketmine\player\Player;
use prokits\player\PracticePlayer;
use think\DbManager;

/**
 * IdentifierProvider
 *
 * Provides a unified way to resolve a player to their internal account UID
 * using either XUID (Xbox Live ID) or player name. This class encapsulates
 * database lookups and caching logic, and allows higher-level services to
 * access player-specific data without manually handling XUID/name conversions.
 *
 * Features:
 * - Supports both online and offline players, with a configurable suffix for
 *   explicitly offline players (`&offline`).
 * - Caches resolved IdentifierProviders in an LRU cache to reduce repeated DB
 *   queries.
 * - Provides closures that can be invoked with a DbManager to resolve the UID
 *   and execute a callback.
 *
 * Usage:
 * ```
 * $provider = IdentifierProvider::autoOrName($player);
 * $result = $provider($db, static fn(int $uid) => doSomethingWithUid($uid));
 * ```
 *
 * @see Identity
 */
final class IdentifierProvider extends ThreadSafe {
	public const OFFLINE_SPECIFY_SUFFIX = '&offline';
	/** @var CacheInterface<self> */
	private static CacheInterface $playerCache;

	private function __construct(private Closure $closure) { }

	/**
	 * Invoke the provider with a database instance to resolve the UID and
	 * execute a callback.
	 *
	 * @param DbManager $db Database manager.
	 * @param Closure $other Callback to execute with resolved UID.
	 * @param mixed $default Default return value if resolution fails.
	 * @return mixed Callback result or default.
	 */
	public function __invoke(DbManager $db, Closure $other, mixed $default = null) {
		return ($this->closure)($db, $other, $default);
	}

	/**
	 * Create an IdentifierProvider for a known XUID.
	 *
	 * @param string $xuid Player XUID.
	 * @return self IdentifierProvider resolving this XUID.
	 * @see IdentifierProvider::autoOrName()
	 */
	public static function xuid(string $xuid) : self {
		return new self(static function(DbManager $db, Closure $other, mixed $default = null) use ($xuid) {
			$eq = $db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
				->cache(true)
				->where(SchemaConstants::COL_XUID, $xuid)
				->limit(1)
				->column(SchemaConstants::COL_UID);
			if (count($eq) > 0) {
				return $other($eq[array_key_first($eq)]);
			}
			\GlobalLogger::get()->debug("Couldn't find account for xuid $xuid.");
			return $default;
		});
	}

	/**
	 * Automatically select an IdentifierProvider from a string, Identity, or
	 * PracticePlayer object. Uses cached provider if available.
	 *
	 * @param Player|PracticePlayer|Identity|string $id Player reference.
	 * @return self IdentifierProvider instance.
	 */
	public static function autoOrName(Player|PracticePlayer|Identity|string $id) : self {
		if (!isset(self::$playerCache)) {
			self::$playerCache = new LRUCache(128);
		}
		if (is_string($id)) {
			$id = Identity::reuse($id, null, false);
		}
		$id = $id->asIdentity();
		$hash = $id->hash();
		$provider = self::$playerCache->get($hash);
		if ($provider !== null) {
			return $provider;
		}
		if ($id->xuid !== null) {
			$provider = self::xuid($id->xuid);
		} else {
			if (str_ends_with(mb_strtolower($id->name), self::OFFLINE_SPECIFY_SUFFIX)) {
				$name = substr($id->name, 0, -strlen(self::OFFLINE_SPECIFY_SUFFIX));
				$guessOnline = false;
			} else {
				$name = $id->name;
				$guessOnline = true;
			}
			$provider = self::name($name, $guessOnline || !$id->xuidKnown);
		}
		self::$playerCache->put($hash, $provider);
		return $provider;
	}

	/**
	 * Create an IdentifierProvider for a player name.
	 *
	 * @param string $name Player name.
	 * @param bool $guessOnline Whether to prioritize players with known XUIDs.
	 * @return self IdentifierProvider resolving this name.
	 * @see IdentifierProvider::autoOrName()
	 */
	public static function name(string $name, bool $guessOnline) : self {
		return new self(static function(DbManager $db, Closure $other, mixed $default = null) use ($guessOnline, $name) {
			return $db->transaction(static function() use ($default, $guessOnline, $name, $db, $other) {
				$eq = $db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
					->cache(10);
				if ($guessOnline) {
					$eq = $eq->whereNotNull(SchemaConstants::COL_XUID);
				}

				$eq = $eq->where(SchemaConstants::COL_PLAYER_NAME, $name)
					->order(SchemaConstants::COL_LAST_MODIFIED_TIME, 'desc')
					->limit(1)
					->column(SchemaConstants::COL_UID);

				if (count($eq) > 0) {
					return $other($eq[array_key_first($eq)]);
				}

				\GlobalLogger::get()->debug("Couldn't find account for online player '$name'.");

				$eq = $db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
					->cache(10)
					->whereNull(SchemaConstants::COL_XUID)
					->where(SchemaConstants::COL_PLAYER_NAME, $name)
					->order(SchemaConstants::COL_LAST_MODIFIED_TIME, 'desc')
					->limit(1)
					->column(SchemaConstants::COL_UID);

				if (count($eq) > 0) {
					return $other($eq[array_key_first($eq)]);
				}
				\GlobalLogger::get()->debug("Couldn't find account for offline player '$name'.");
				return $default;
			}) ?? $default;
		});
	}
}