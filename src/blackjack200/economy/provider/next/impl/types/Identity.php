<?php

namespace blackjack200\economy\provider\next\impl\types;

use pocketmine\player\PlayerInfo;
use pocketmine\player\XboxLivePlayerInfo;

/**
 * Identity
 *
 * Represents a player's identity, including their username and optional XUID
 * (Xbox Live unique identifier). This class is used to normalize player
 * information for systems such as account management, rank assignments, and
 * caching.
 *
 * The class provides a reusable singleton instance to minimize object creation
 * overhead when repeatedly processing player information.
 *
 * for obtaining a player's identity, {@see IdentifierProvider}
 */
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

	/**
	 * Refresh the internal hash of the identity.
	 *
	 * This method recomputes a hash string based on the current name, XUID,
	 * and XUID known status.
	 * @internal
	 */
	public function refresh() : void {
		$this->hash = "$this->name:" . ($this->xuid ?? 'null') . ((int) ($this->xuidKnown));
	}

	/**
	 * Construct an Identity from a PlayerInfo instance.
	 *
	 * @internal
	 * @param PlayerInfo $info PlayerInfo object, may be XboxLivePlayerInfo.
	 * @return self New Identity instance.
	 * @see IdentifierProvider::autoOrName()
	 */
	public static function fromPlayerInfo(PlayerInfo $info) : self {
		return new Identity($info->getUsername(), $info instanceof XboxLivePlayerInfo ? $info->getXuid() : null, true);
	}

	/**
	 * Construct an Identity from PlayerInfo and reuse the singleton instance.
	 *
	 * @internal
	 * @param PlayerInfo $info PlayerInfo object.
	 * @return self Reused Identity instance updated with new info.
	 * @see IdentifierProvider::autoOrName()
	 */
	public static function fromPlayerInfoReuse(PlayerInfo $info) : self {
		return self::reuse($info->getUsername(), $info instanceof XboxLivePlayerInfo ? $info->getXuid() : null, true);
	}

	/**
	 * Reuse the singleton Identity instance with new values.
	 *
	 * If the singleton does not exist, it will be created. Otherwise, it is
	 * updated in place.
	 *
	 * @internal
	 * @param string $name Player username.
	 * @param string|null $xuid Optional XUID.
	 * @param bool $xuidKnown Whether XUID is known.
	 * @return self Singleton instance with updated values.
	 * @see IdentifierProvider::autoOrName()
	 */
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