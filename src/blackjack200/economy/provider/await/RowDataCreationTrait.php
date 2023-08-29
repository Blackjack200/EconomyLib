<?php

namespace blackjack200\economy\provider\await;

use blackjack200\economy\provider\mysql\MySQLTypes;

trait RowDataCreationTrait {
	/**
	 * @return static<bool>
	 */
	public static function bool(
		AwaitProviderInterface $provider,
		string                 $rowName,
		bool                   $default = false
	) : static {
		return new static(
			$provider,
			$rowName,
			MySQLTypes::BOOLEAN,
			(int) $default,
			static fn($raw) => (((int) $raw) & 1)
		);
	}

	/**
	 * @template NT of (int|float)
	 * @param NT $default
	 * @return static<NT>
	 */
	public static function signed(
		AwaitProviderInterface $provider,
		string                 $rowName,
		string                 $type,
		                       $default = 0
	) : static {
		$int = is_int($default);
		return new static(
			$provider,
			$rowName,
			$type,
			$default,
			static fn($raw) => ($int ? (int) $raw : (float) $raw)
		);
	}

	/**
	 * @template NT of (int|float)
	 * @param NT $default
	 * @return static<NT>
	 */
	public static function unsigned(
		AwaitProviderInterface $provider,
		string                 $rowName,
		string                 $type,
		int|float              $default = 0
	) : static {
		return static::signed(
			$provider,
			$rowName,
			MySQLTypes::unsigned($type),
			$default
		);
	}

	/**
	 * @return static<int>
	 */
	public static function timestamp(
		AwaitProviderInterface $provider,
		string                 $rowName,
		int                    $default = 0
	) : static {
		return static::signed(
			$provider,
			$rowName,
			MySQLTypes::unsigned(MySQLTypes::INT64),
			$default
		);
	}

	/**
	 * @return static<string>
	 */
	public static function blob(
		AwaitProviderInterface $provider,
		string                 $rowName,
		string                 $length,
		string                 $default = ''
	) : static {
		if (strlen($default) > $length) {
			throw new \InvalidArgumentException('Invalid default string length: ' . strlen($default . ' vs ' . $length));
		}
		return static::signed(
			$provider,
			$rowName,
			MySQLTypes::blob($length),
			$default
		);
	}

	/**
	 * @return static<string>
	 */
	public static function char(
		AwaitProviderInterface $provider,
		string                 $rowName,
		string                 $length,
		string                 $default = ''
	) : static {
		if (strlen($default) > $length) {
			throw new \InvalidArgumentException('Invalid default string length: ' . strlen($default . ' vs ' . $length));
		}
		return static::signed(
			$provider,
			$rowName,
			MySQLTypes::char($length),
			$default
		);
	}

	/**
	 * @return static<string>
	 */
	public static function varchar(
		AwaitProviderInterface $provider,
		string                 $rowName,
		string                 $length,
		string                 $default = ''
	) : static {
		if (strlen($default) > $length) {
			throw new \InvalidArgumentException('Invalid default string length: ' . strlen($default . ' vs ' . $length));
		}
		return static::signed(
			$provider,
			$rowName,
			MySQLTypes::varchar($length),
			$default
		);
	}
}