<?php

namespace blackjack200\economy\provider\await;

use Generator;
use libasync\await\Await;
use libasync\runtime\AsyncRuntime;
use think\db\exception\DataNotFoundException;
use think\DbManager;

class AwaitMySQLProvider implements AwaitProviderInterface {
	protected AwaitTableMigrator $migrator;

	public function __construct(
		private readonly string       $table,
		private readonly string       $index,
		private readonly AsyncRuntime $runtime,
	) {
		$this->migrator = new AwaitTableMigrator($table, $this->runtime);
	}

	public function keys() : Generator {
		$table = $this->table;
		$index = $this->index;
		return Await::async(static function(DbManager $db) use ($index, $table) : array {
			$arr = $db->table($table)->column($index);
			sort($arr, SORT_STRING);
			return $arr;
		}, $this->runtime);
	}

	public function has(string $name) : Generator {
		$table = $this->table;
		$index = $this->index;
		return Await::async(static function(DbManager $db) use ($name, $index, $table) : bool {
			try {
				$db->table($table)->where($index, $name)
					->findOrFail($name);
				return true;
			} catch (DataNotFoundException) {
			}
			return false;
		}, $this->runtime);
	}

	public function get(string $name, string $type) : Generator {
		$table = $this->table;
		$index = $this->index;
		return Await::async(static function(DbManager $db) use ($index, $table, $type, $name) {
			$ret = $db->table($table)->limit(1)
				->where($index, $name)
				->column($type);
			return array_pop($ret);
		}, $this->runtime);
	}

	public function getALL(string $name) : Generator {
		$table = $this->table;
		$index = $this->index;
		return Await::async(static function(DbManager $db) use ($index, $table, $name) : array {
			$ret = $db->table($table)->limit(1)
				->where($index, $name)
				->findOrEmpty();
			unset($ret[$index]);
			return $ret;
		}, $this->runtime);
	}

	public function getTable() : string {
		return $this->table;
	}

	public function getIndex() : string {
		return $this->index;
	}

	public function initialize(string $name) : \Generator {
		$table = $this->table;
		$index = $this->index;
		return Await::async(static function(DbManager $db) use ($index, $table, $name) : bool {
			if ($db->table($table)->extra('IGNORE')->insert(
				[$index => $name]
			)) {
				return true;
			}
			return false;
		}, $this->runtime);
	}

	public function add(string $name, string $type, int $delta) : \Generator {
		$table = $this->table;
		$index = $this->index;
		return Await::async(static function(DbManager $db) use ($index, $table, $delta, $type, $name) : bool {
			$db->table($table)->extra('IGNORE')->insert(
				[$index => $name]
			);
			if ($delta === 0) {
				return true;
			}
			$retry = 1 << 8;
			while ($retry-- > 0) {
				$old = $db->table($table)
					->where($index, $name)->limit(1)
					->column($type);
				if (empty($old)) {
					return false;
				}
				//CAS
				$old = array_pop($old);

				if ($db->table($table)
						->where($index, $name)
						->where($type, $old)
						->inc($type, $delta)
						->limit(1)
						->update() === 1) {
					return true;
				}
			}
			return false;
		}, $this->runtime);
	}

	public function set(string $name, string $col, $val) : \Generator {
		$table = $this->table;
		$index = $this->index;
		return Await::async(static function(DbManager $db) use ($index, $table, $val, $col, $name) : bool {
			try {
				$db->table($table)->where($index, $name)->findOrFail();
				return (bool) $db->table($table)->where($index, $name)->update([$col => $val]);
			} catch (DataNotFoundException) {
				return (bool) $db->table($table)->insert(
					[$index => $name, $col => $val]
				);
			}
		}, $this->runtime);
	}

	public function asort(string $type, int $limit) : Generator {
		return $this->sort('ASC', $limit, $type);
	}

	private function sort(string $mode, int $limit, string $type) : Generator {
		$table = $this->table;
		$index = $this->index;
		return Await::async(static function(DbManager $db) use ($index, $mode, $limit, $table, $type) : array {
			return ($db->table($table)
				->order($type, $mode)
				->limit($limit)
				->select()
				->column($type, $index));
		}, $this->runtime);
	}

	public function dsort(string $type, int $limit) : Generator {
		return $this->sort('DESC', $limit, $type);
	}

	public function remove(string $name) : Generator {
		$table = $this->table;
		$index = $this->index;
		return Await::async(static function(DbManager $db) use ($index, $name, $table) : bool {
			if ($db->table($table)->where($index, $name)->delete() !== 0) {
				return true;
			}
			return false;
		}, $this->runtime);
	}

	public function rename(string $old, string $new) : Generator {
		$table = $this->table;
		$index = $this->index;
		return Await::async(static function(DbManager $db) use ($old, $new, $index, $table) : bool {
			$db->table($table)->extra('IGNORE')->insert(
				[$index => $old]
			);
			$retry = 1 << 8;
			while ($retry-- > 0) {
				if ($db->table($table)
						->where($index, $old)
						->data([$index => $new])
						->limit(1)
						->update() === 1) {
					return true;
				}
			}
			return false;
		}, $this->runtime);
	}

	public function addColumn(string $col, string $type, string $default) : Generator {
		return $this->migrator->addColumns($col, $type, $default);
	}

	public function removeColumn(string $col) : Generator {
		return $this->migrator->removeColumns($col);
	}

	public function hasColumn(string $col) : Generator {
		return $this->migrator->hasColumns($col);
	}

	public function getColumns() : Generator {
		return $this->migrator->getColumns();
	}

	public function getRuntime() : AsyncRuntime {
		return $this->runtime;
	}
}