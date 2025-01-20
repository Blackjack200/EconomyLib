<?php

namespace blackjack200\economy\provider\await\column\impl;

use blackjack200\economy\provider\await\holder\Behaviour;

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
			new Behaviour(
				static fn($raw) => (bool) $raw,
				static fn($raw) => (bool) (((int) $raw) & 1)
			)
		);
	}

	/**
	 * @return MysqlIntegerColumn<int>
	 */
	public static function int(
		string $key,
		int    $default = 0,
		bool   $signed = false
	) : MysqlIntegerColumn {
		return new MysqlIntegerColumn(
			$key,
			$default,
			Behaviour::int($signed),
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
			Behaviour::string()
		);
	}

	/**
	 * @return MysqlColumn<array>
	 */
	public static function array(
		string $key,
		array  $default = []
	) : MysqlColumn {
		return new MysqlColumn(
			$key,
			$default,
			Behaviour::array()
		);
	}
}