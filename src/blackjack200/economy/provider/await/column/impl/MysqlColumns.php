<?php

namespace blackjack200\economy\provider\await\column\impl;

class MysqlColumns {
	/**
	 * @return MysqlColumn<bool>
	 */
	public static function bool(
		string $key,
		bool   $default = false
	) : MysqlColumn {
		return new MysqlColumn(
			$key,
			$default,
			static fn($raw) => (bool) (((int) $raw) & 1)
		);
	}

	/**
	 * @return MysqlIntegerColumn<int>
	 */
	public static function int(
		string $key,
		int  $default = 0,
		bool $signed = false
	) : MysqlIntegerColumn {
		return new MysqlIntegerColumn(
			$key,
			$default,
			static fn($raw) => ((int) $raw),
			$signed
		);
	}

	/**
	 * @return MysqlColumn<string>
	 */
	public static function string(
		string $key,
		string $default = ''
	) : MysqlColumn {
		return new MysqlColumn(
			$key,
			$default,
			static fn($raw) => ((string) $raw)
		);
	}
}