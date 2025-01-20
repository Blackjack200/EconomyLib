<?php

namespace blackjack200\economy\provider\next\impl\types;

use pocketmine\player\PlayerInfo;
use pocketmine\player\XboxLivePlayerInfo;

class Identity {
	private static self $reuse;
	protected string $hash;

	public function __construct(
		public string  $name,
		public ?string $xuid = null,
		public bool    $xuidKnown = false,
	) {
		$this->refresh();
	}

	public function refresh() : void {
		$this->hash = "$this->name:" . ($this->xuid ?? 'null') . ((int) ($this->xuidKnown));
	}

	public static function fromPlayerInfo(PlayerInfo $info) : self {
		return new Identity($info->getUsername(), $info instanceof XboxLivePlayerInfo ? $info->getXuid() : null, true);
	}

	public static function fromPlayerInfoReuse(PlayerInfo $info) : self {
		return self::reuse($info->getUsername(), $info instanceof XboxLivePlayerInfo ? $info->getXuid() : null, true);
	}

	public static function reuse(string $name, ?string $xuid, bool $xuidKnown = true) : self {
		if (!isset(self::$reuse)) {
			self::$reuse = new self($name, $xuid, $xuidKnown);
			return self::$reuse;
		}
		self::$reuse->name = $name;
		self::$reuse->xuid = $xuid;
		self::$reuse->xuidKnown = $xuidKnown;
		self::$reuse->refresh();
		return self::$reuse;
	}

	public function hash() : string {
		return $this->hash;
	}

	public function asIdentity() : self {
		return $this;
	}
}