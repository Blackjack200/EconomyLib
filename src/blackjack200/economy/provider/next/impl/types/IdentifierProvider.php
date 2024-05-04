<?php

namespace blackjack200\economy\provider\next\impl\types;

use blackjack200\economy\provider\await\column\WeakOrStrongCache;
use Closure;
use pmmp\thread\ThreadSafe;
use pocketmine\player\XboxLivePlayerInfo;
use prokits\player\PracticePlayer;
use think\DbManager;

final class IdentifierProvider extends ThreadSafe {
	/** @var WeakOrStrongCache<PracticePlayer,string,self> */
	private static WeakOrStrongCache $playerCache;

	private function __construct(private Closure $closure) { }

	public function __invoke(DbManager $db, Closure $other, mixed $default = null) {
		return ($this->closure)($db, $other, $default);
	}

	public static function xuid(string $xuid) : self {
		return new self(static function(DbManager $db, Closure $other, mixed $default = null) use ($xuid) {
			return $other($xuid) ?? $default;
		});
	}

	public static function autoOrName(PracticePlayer|string $id) : self {
		if (!isset(self::$playerCache)) {
			self::$playerCache = new WeakOrStrongCache(100, 100000);
		}
		if ($id instanceof PracticePlayer) {
			$cc = self::$playerCache->get($id);
			if ($cc !== null) {
				return $cc;
			}
			$info = $id->getPlayerInfo();
			if ($info instanceof XboxLivePlayerInfo) {
				$provider = self::xuid($info->getXuid());
			} else {
				$provider = self::name($id->getName());
			}
			self::$playerCache->put($id, $provider);
			return $provider;
		}
		$provider = self::$playerCache->get($id);
		if ($provider !== null) {
			return $provider;
		}
		$provider = self::name($id);

		self::$playerCache->put($id, $provider);
		return $provider;
	}

	public static function name(string $name) : self {
		return new self(static function(DbManager $db, Closure $other, mixed $default = null) use ($name) {
			return $db->transaction(static function() use ($default, $name, $db, $other) {
				$eq = $db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
					->where(SchemaConstants::COL_PLAYER_NAME, $name)
					->order(SchemaConstants::COL_LAST_MODIFIED_TIME, 'desc')
					->column(SchemaConstants::COL_XUID);
				/*if (count($eq) > 1) {
					throw new LogicException("Name: $name should not be associated with " . var_export($eq, true));
				}*/
				if (count($eq) > 0) {
					$xuid = array_pop($eq);
					return $other($xuid);
				}
				return $default;
			});
		});
	}
}