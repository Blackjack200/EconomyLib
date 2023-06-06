<?php

namespace blackjack200\economy\provider\await;

use Generator;
use InvalidArgumentException;
use libasync\await\Await;
use libasync\runtime\AsyncRuntime;
use think\DbManager;

readonly class AwaitTableMigrator {

	public function __construct(
		private string       $table,
		private AsyncRuntime $runtime,
	) {
	}

	public function addColumns(string $column, string $type, string $default) : \Generator {
		$table = $this->table;
		return Await::async(static function(DbManager $db) use ($type, $column, $table, $default) : bool {
			$cfg = $db->getConfig();
			$dbName = $cfg['connections'][$cfg['default']]['database'];
			$has = !empty($db->query('select * from information_schema.COLUMNS where TABLE_SCHEMA = ? && TABLE_NAME = ? && COLUMN_NAME = ?;',
				[$dbName, $table, $column]
			));
			if ($has) {
				return true;
			}
			$format = "alter table %s add column `%s` $type not null";
			if ($default !== '') {
				$format .= " default " . addslashes($default);
			}
			if ($db->execute(sprintf($format, $table, addslashes($column))) === 0) {
				return true;
			}
			return false;
		}, $this->runtime);
	}

	public function removeColumns(string $column) : Generator {
		$table = $this->table;
		return Await::async(static function(DbManager $db) use ($table, $column) : bool {
			$cfg = $db->getConfig();
			$dbName = $cfg['connections'][$cfg['default']]['database'] ?? null;
			if ($dbName === null) {
				throw new InvalidArgumentException();
			}
			$notFound = empty($db->query('select * from information_schema.COLUMNS where TABLE_SCHEMA =? && TABLE_NAME = ? && COLUMN_NAME = ?;',
				[$dbName, $table, $column]
			));
			if ($notFound) {
				return false;
			}
			if ($db->execute(sprintf(
					'alter table %s drop column `%s`',
					$table, addslashes($column)
				)) === 0) {
				return true;
			}
			return false;
		}, $this->runtime);
	}

	public function hasColumns(string $column) : Generator {
		$table = $this->table;
		return Await::async(static function(DbManager $db) use ($table, $column) : bool {
			$cfg = $db->getConfig();
			$dbName = $cfg['connections'][$cfg['default']]['database'];
			return !empty($db->query('select * from information_schema.COLUMNS where TABLE_SCHEMA = ? && TABLE_NAME = ? && COLUMN_NAME = ?;',
				[$dbName, $table, $column]
			));
		}, $this->runtime);
	}

	public function getColumns() : Generator {
		$table = $this->table;
		return Await::async(static function(DbManager $db) use ($table) : array {
			$result = $db->query("show columns from $table");
			$names = [];
			foreach ($result as $entry) {
				$name = $entry['Field'];
				$key = $entry['Key'];
				if ($key === '') {
					$names[] = $name;
				}
			}
			return $names;
		}, $this->runtime);
	}
}