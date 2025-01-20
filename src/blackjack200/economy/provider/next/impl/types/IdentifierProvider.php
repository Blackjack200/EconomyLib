<?php

namespace blackjack200\economy\provider\next\impl\types;

use blackjack200\cache\CacheInterface;
use blackjack200\cache\LRUCache;
use Closure;
use pmmp\thread\ThreadSafe;
use prokits\player\PracticePlayer;
use think\DbManager;

final class IdentifierProvider extends ThreadSafe {
	public const OFFLINE_SPECIFY_SUFFIX = '&offline';
	/** @var CacheInterface<self> */
	private static CacheInterface $playerCache;

	private function __construct(private Closure $closure) { }

	public function __invoke(DbManager $db, Closure $other, mixed $default = null) {
		return ($this->closure)($db, $other, $default);
	}

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

	public static function autoOrName(PracticePlayer|Identity|string $id) : self {
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