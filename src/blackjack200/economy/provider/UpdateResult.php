<?php

namespace blackjack200\economy\provider;

enum UpdateResult: string {
	case SUCCESS = "success";
	case NO_CHANGE = 'no_change';
	case INTERNAL_ERROR = "internal_error";

	public static function fromRow(int $affectedRow) : self {
		return match (true) {
			$affectedRow === 1 => self::SUCCESS,
			$affectedRow === 0 => self::NO_CHANGE,
			default => self::INTERNAL_ERROR,
		};
	}

	public function failed() : bool {
		return $this === self::INTERNAL_ERROR || $this === self::NO_CHANGE;
	}

	public function success() : bool {
		return $this === self::SUCCESS;
	}
}