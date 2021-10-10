<?php

namespace blackjack200\economy\provider\mysql;

final class MySQLTypes {
	public const SUFFIX_UNSIGNED = 'UNSIGNED';
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
}