<?php

namespace blackjack200\economy\provider\await;

trait RowDataCreationTrait {
	/**
	 * @return static<bool>
	 */
	public static function bool(
		string $key,
		bool   $default = false
	) : static {
		return new static(
			$key,
			(int) $default,
			static fn($raw) => (((int) $raw) & 1)
		);
	}

	/**
	 * @return static<int>
	 */
	public static function int(
		string $key,
		int    $default = 0
	) : static {
		return new static(
			$key,
			$default,
			static fn($raw) => ((int) $raw)
		);
	}

	/**
	 * @return static<string>
	 */
	public static function string(
		string $key,
		string $default = ''
	) : static {
		return new static(
			$key,
			$default,
			static fn($raw) => ((string) $raw)
		);
	}
}