<?php

namespace blackjack200\economy\provider\await\holder;

/**
 * @template RawType
 * @template Type
 */
class Behaviour {
	public function __construct(
		/** @var \Closure(Type):RawType $encoder */
		public \Closure $encoder,
		/** @var \Closure(null|RawType):Type $decoder */
		public \Closure $decoder,
	) {
	}

	public static function passthrough() : self {
		return new self(static fn($v) => $v, static fn($v) => $v);
	}

	public static function string() : self {
		return new self(static fn($v) => (string) $v, static fn($v) => (string) $v);
	}

	public static function int(bool $signed = true) : self {
		return new self(static fn($v) => $signed ? (int) $v : max(0, (int) $v), static fn($v) => $signed ? (int) $v : max(0, (int) $v));
	}

	public static function bool() : self {
		return new self(static fn($v) => (bool) $v, static fn($v) => (bool) $v);
	}

	public static function float() : self {
		return new self(static fn($v) => (float) $v, static fn($v) => (float) $v);
	}

	public static function array() : self {
		return new self(static fn($v) => (array) $v, static fn($v) => (array) $v);
	}
}