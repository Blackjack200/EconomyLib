<?php


namespace blackjack200\economy\provider\mysql;


use blackjack200\economy\provider\ProviderInterface;
use libasync\promise\Promise;
use libasync\promise\PromiseInterface;
use think\db\exception\DataNotFoundException;
use think\DbManager;

class MySQLProvider implements ProviderInterface {
	public string $index;
	protected string $table;
	protected TableMigrator $migrator;
	protected string $launcher;

	public function __construct(string $table, string $index, string $launcher = DBExecutorLauncher::class) {
		$this->index = $index;
		$this->table = $table;
		$this->migrator = new TableMigrator($table);
		$this->launcher = $launcher;
	}

	public function has(string $name) : PromiseInterface {
		$table = $this->table;
		$index = $this->index;
		return $this->newPromise()->then(static function (callable $resolve, callable $reject, DbManager $db) use ($name, $index, $table) : void {
			try {
				$db->table($table)->where($index, $name)
					->findOrFail($name);
				$resolve(true);
			} catch (DataNotFoundException) {
			}
			$resolve(false);
		});
	}

	protected function newPromise() : Promise {
		return (new Promise())->bind($this->launcher);
	}

	public function get(string $name, string $type) : PromiseInterface {
		$table = $this->table;
		$index = $this->index;
		return $this->newPromise()->then(static function (callable $resolve, callable $reject, DbManager $db) use ($index, $table, $type, $name) : void {
			$ret = $db->table($table)->limit(1)
				->where($index, $name)
				->column($type);
			$resolve(array_pop($ret));
		});
	}

	public function getALL(string $name) : PromiseInterface {
		$table = $this->table;
		$index = $this->index;
		return $this->newPromise()->then(static function (callable $resolve, callable $reject, DBManager $db) use ($index, $table, $name) : void {
			$ret = $db->table($table)->limit(1)
				->where($index, $name)
				->findOrEmpty();
			unset($ret[$index]);
			$resolve($ret);
		});
	}

	public function set(string $name, string $col, $val) : PromiseInterface {
		$table = $this->table;
		$index = $this->index;
		return $this->newPromise()->then(static function (callable $resolve, callable $reject, DBManager $db) use ($index, $table, $val, $col, $name) : void {
			try {
				$db->table($table)->where($index, $name)->findOrFail();
				$resolve((bool) $db->table($table)->where($index, $name)->update([$col => $val]));
			} catch (DataNotFoundException) {
				$resolve((bool) $db->table($table)->insert(
					[$index => $name, $col => $val]
				));
			}
		});
	}

	public function remove(string $name) : PromiseInterface {
		$table = $this->table;
		$index = $this->index;
		return $this->newPromise()->then(static function (callable $resolve, callable $reject, DBManager $db) use ($index, $name, $table) : void {
			if ($db->table($table)->where($index, $name)->delete() !== 0) {
				$resolve();
			}
			$reject();
		});
	}

	public function rename(string $old, string $new) : PromiseInterface {
		$table = $this->table;
		$index = $this->index;
		return $this->newPromise()->then(static function (callable $resolve, callable $reject, DBManager $db) use ($old, $new, $index, $table) : void {
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
					$resolve();
				}
			}
			$reject();
		});
	}

	public function initialize(string $name) : PromiseInterface {
		$table = $this->table;
		$index = $this->index;
		return $this->newPromise()->then(static function (callable $resolve, callable $reject, DBManager $db) use ($index, $table, $name) : void {
			if ($db->table($table)->extra('IGNORE')->insert(
				[$index => $name]
			)) {
				$resolve();
			}
			$reject();
		});
	}

	public function add(string $name, string $type, int $delta) : PromiseInterface {
		$table = $this->table;
		$index = $this->index;
		return $this->newPromise()->then(static function (callable $resolve, callable $reject, DBManager $db) use ($index, $table, $delta, $type, $name) : void {
			$db->table($table)->extra('IGNORE')->insert(
				[$index => $name]
			);
			if($delta === 0){
				$resolve();
			}
			$retry = 1 << 8;
			while ($retry-- > 0) {
				$old = $db->table($table)
					->where($index, $name)->limit(1)
					->column($type);
				if (empty($old)) {
					$reject();
				}
				//CAS
				$old = array_pop($old);

				if ($db->table($table)
						->where($index, $name)
						->where($type, $old)
						->inc($type, $delta)
						->limit(1)
						->update() === 1) {
					$resolve();
				}
			}
			$reject();
		});
	}

	public function asort(string $type, int $limit) : PromiseInterface {
		return $this->sort('ASC', $limit, $type);
	}

	private function sort(string $mode, int $limit, string $type) : Promise {
		$table = $this->table;
		$index = $this->index;
		return $this->newPromise()->then(static function (callable $resolve, callable $reject, DBManager $db) use ($index, $mode, $limit, $table, $type) : void {
			$resolve($db->table($table)
				->order($type, $mode)
				->limit($limit)
				->select()
				->column($type, $index));
		});
	}

	public function dsort(string $type, int $limit) : PromiseInterface {
		return $this->sort('DESC', $limit, $type);
	}

	public function addColumn(string $col, string $type, string $default) : PromiseInterface {
		return $this->migrator->addColumns($col, $type, $default);
	}

	public function removeColumn(string $col) : PromiseInterface {
		return $this->migrator->removeColumns($col);
	}

	public function hasColumn(string $col) : PromiseInterface {
		return $this->migrator->hasColumns($col);
	}

	public function getColumns() : PromiseInterface {
		return $this->migrator->getColumns();
	}

	public function getLauncher() : string {
		return $this->launcher;
	}

	public function getTable() : string {
		return $this->table;
	}

	public function getIndex() : string {
		return $this->index;
	}

	public function keys() : PromiseInterface {
		$table = $this->table;
		$index = $this->index;
		return $this->newPromise()->then(static function (callable $resolve, callable $reject, DBManager $db) use ($index, $table) : void {
			$arr = $db->table($table)->column($index);
			sort($arr, SORT_STRING);
			$resolve($arr);
		});
	}
}