<?php

namespace blackjack200\economy\provider\mysql;

final class MySQLTypes {
	private const SUFFIX_UNSIGNED = 'UNSIGNED';
	public const BOOLEAN = 'TINYINT(1)';
	public const INT8 = 'TINYINT';
	public const INT16 = 'SMALLINT';
	public const INT32 = 'INT';
	public const INT64 = 'BIGINT';
	public const FLOAT32 = 'FLOAT';
	public const FLOAT64 = 'DOUBLE';
	public const DECIMAL = 'DECIMAL';
	public const TIMESTAMP = 'TIMESTAMP';

	private function __construct() {
	}

	public static function unsigned(string $type) : string {
		return $type . ' ' . self::SUFFIX_UNSIGNED;
	}

	private static function complex(int $length, int $max, string $type) : string {
		if ($length < 0 || $length > $max) {
			throw new \InvalidArgumentException("Invalid char length $length");
		}
		return "$type($length)";
	}

	/**
	 * just like str_len
	 */
	public static function char(int $length) : string {
		return self::complex($length, 255, "CHAR");
	}

	/**
	 * just like mb_strlen
	 */
	public static function varchar(int $length) : string {
		return self::complex($length, 65535, "VARCHAR");
	}

	/**
	 * just like binary-safe string buf
	 */
	public static function blob(int $length) : string {
		return self::complex($length, 65535, "BLOB");
	}
}