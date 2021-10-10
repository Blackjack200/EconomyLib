<?php

namespace blackjack200\economy\provider\mysql;

use InvalidArgumentException;
use libasync\Promise;
use libasync\PromiseInterface;
use think\DbManager;

class TableMigrator {
	protected string $table;

	public function __construct(string $table) {
		$this->table = $table;
	}

	private function newPromise() : Promise {
		return (new Promise())->bind(DBExecutorLauncher::class);
	}

	public function addColumns(string $column, string $type, mixed $default) : PromiseInterface {
		$table = $this->table;
		return $this->newPromise()->then(static function (callable $resolve, callable $reject, DBManager $db) use ($type, $column, $table, $default) : void {
			$cfg = $db->getConfig();
			$dbName = $cfg['connections'][$cfg['default']]['database'];
			$has = !empty($db->query('select * from information_schema.COLUMNS where TABLE_SCHEMA =? && TABLE_NAME = ? && COLUMN_NAME = ?;',
				[$dbName, $table, $column]
			));
			if ($has) {
				$resolve();
			}
			$format = "alter table ? add column ? $type not null default ?";
			if ($db->execute($format, $table, $column, $default) === 0) {
				$resolve();
			}
			$reject();
		});
	}

	public function removeColumns(string $column) : PromiseInterface {
		$table = $this->table;
		return $this->newPromise()->then(static function (callable $resolve, callable $reject, DBManager $db) use ($table, $column) : void {
			$cfg = $db->getConfig();
			$dbName = $cfg['connections'][$cfg['default']]['database'] ?? null;
			if ($dbName === null) {
				throw new InvalidArgumentException();
			}
			$notFound = empty($db->query('select * from information_schema.COLUMNS where TABLE_SCHEMA =? && TABLE_NAME = ? && COLUMN_NAME = ?;',
				[$dbName, $table, $column]
			));
			if ($notFound) {
				$reject();
			}
			if ($db->execute(sprintf(
					'alter table %s drop column `%s`',
					$table, addslashes($column)
				)) === 0) {
				$resolve();
			}
			$reject();
		});
	}

	public function hasColumns(string $column) : PromiseInterface {
		$table = $this->table;
		return $this->newPromise()->then(static function (callable $resolve, callable $reject, DBManager $db) use ($table, $column) : void {
			$cfg = $db->getConfig();
			$dbName = $cfg['connections'][$cfg['default']]['database'];
			if (!empty($db->query('select * from information_schema.COLUMNS where TABLE_SCHEMA = ? && TABLE_NAME = ? && COLUMN_NAME = ?;',
				[$dbName, $table, $column]
			))) {
				$resolve();
			}
			$reject();
		});
	}

	public function getColumns() : PromiseInterface {
		$table = $this->table;
		return $this->newPromise()->then(static function (callable $resolve, callable $reject, DBManager $db) use ($table) : void {
			$result = $db->query("show columns from $table");
			$names = [];
			foreach ($result as $entry) {
				$name = $entry['Field'];
				$key = $entry['Key'];
				if ($key === '') {
					$names[] = $name;
				}
			}
			$resolve($names);
		});
	}
}