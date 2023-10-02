<?php

namespace blackjack200\economy\provider\next\impl\types;

use Closure;
use pmmp\thread\ThreadSafe;
use pocketmine\player\XboxLivePlayerInfo;
use prokits\player\PracticePlayer;
use think\DbManager;

final class IdentifierProvider extends ThreadSafe {
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
		if ($id instanceof PracticePlayer) {
			$info = $id->getPlayerInfo();
			if ($info instanceof XboxLivePlayerInfo) {
				return self::xuid($info->getXuid());
			}
			return self::name($id->getName());
		}
		return self::name($id);
	}

	public static function autoOrXuid(PracticePlayer|string $id) : self {
		if ($id instanceof PracticePlayer) {
			$info = $id->getPlayerInfo();
			if ($info instanceof XboxLivePlayerInfo) {
				return self::xuid($info->getXuid());
			}
			return self::name($id->getName());
		}
		return self::xuid($id);
	}

	public static function name(string $name) : self {
		return new self(static function(DbManager $db, Closure $other, mixed $default = null) use ($name) {
			return $db->transaction(static function() use ($default, $name, $db, $other) {
				$eq = $db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
					->cache()
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