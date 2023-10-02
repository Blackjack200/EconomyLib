<?php

namespace blackjack200\economy\provider\next\impl\types;

use Closure;
use LogicException;
use pmmp\thread\ThreadSafe;
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

	public static function name(string $name) : self {
		return new self(static function(DbManager $db, Closure $other, mixed $default = null) use ($name) {
			return $db->transaction(static function() use ($default, $name, $db, $other) {
				$eq = $db->table(SchemaConstants::TABLE_ACCOUNT_METADATA)
					->where(SchemaConstants::COL_PLAYER_NAME, $name)
					->cache(60)
					->column(SchemaConstants::COL_XUID);
				if (count($eq) > 1) {
					throw new LogicException("Name: $name should not be associated with " . var_export($eq, true));
				}
				if (count($eq) === 1) {
					$xuid = array_pop($eq);
					return $other($xuid);
				}
				return $default;
			});
		});
	}
}