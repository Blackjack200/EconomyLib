<?php

namespace blackjack200\economy\provider;

/**
 * UpdateResult enum
 *
 * Represents the possible outcomes of a storage operation.
 *
 * # Variants
 *
 * - `SUCCESS`: Operation completed successfully.
 * - `NO_CHANGE`: Operation did not modify any value.
 * - `INTERNAL_ERROR`: Operation failed due to an internal error.
 *
 * # Methods
 *
 * - `fromRow(int $affectedRow)`: Construct an `UpdateResult` from the number of affected
 *   rows in a database operation.
 *
 * - `failed()`: Returns `true` if the operation did not succeed (`NO_CHANGE` or `INTERNAL_ERROR`).
 *
 * - `success()`: Returns `true` if the operation was successful (`SUCCESS`).
 */
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