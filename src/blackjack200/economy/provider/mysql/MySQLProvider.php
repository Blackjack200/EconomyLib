<?php


namespace blackjack200\economy\provider\mysql;


use blackjack200\economy\provider\ProviderInterface;
use libasync\Promise;
use libasync\PromiseInterface;
use think\db\exception\DataNotFoundException;
use think\DbManager;

class MySQLProvider implements ProviderInterface {
	protected string $table;

	public function __construct(string $table) {
		$this->table = $table;
	}

	public function get(string $name, string $type) : PromiseInterface {
		$table = $this->table;
		return $this->newPromise()->then(static function (callable $resolve, callable $reject, DbManager $db) use ($table, $type, $name) : void {
			$ret = $db->table($table)->limit(1)
				->where('player_name', $name)
				->column($type);
			$resolve(array_pop($ret));
		});
	}

	public function getALL(string $name) : PromiseInterface {
		$table = $this->table;
		return $this->newPromise()->then(static function (callable $resolve, callable $reject, DBManager $db) use ($table, $name) : void {
			$resolve($db->table($table)->limit(1)
				->where('player_name', $name)
				->findOrEmpty());
		});
	}

	public function set(string $name, string $type, int $val) : PromiseInterface {
		$table = $this->table;
		return $this->newPromise()->then(static function (callable $resolve, callable $reject, DBManager $db) use ($table, $val, $type, $name) : void {
			try {
				$db->table($table)->where('player_name', $name)->findOrFail();
				$resolve((bool) $db->table($table)->where('player_name', $name)->update([$type => $val]));
			} catch (DataNotFoundException $ex) {
				$resolve((bool) $db->table($table)->insert(
					['player_name' => $name, $type => $val]
				));
			}
		});
	}

	public function initialize(string $name) : PromiseInterface {
		$table = $this->table;
		return $this->newPromise()->then(static function (callable $resolve, callable $reject, DBManager $db) use ($table, $name) : void {
			if ($db->table($table)->extra('IGNORE')->insert(
				['player_name' => $name]
			)) {
				$resolve();
			}
			$reject();
		});
	}

	public function add(string $name, string $type, int $val) : PromiseInterface {
		$table = $this->table;
		return $this->newPromise()->then(static function (callable $resolve, callable $reject, DBManager $db) use ($table, $val, $type, $name) : void {
			$db->table($table)->extra('IGNORE')->insert(
				['player_name' => $name]
			);
			$retry = 1 << 8;
			while ($retry-- > 0) {
				$old = $db->table($table)
					->where('player_name', $name)->limit(1)
					->column($type);
				if (empty($old)) {
					$reject();
				}
				//CAS
				$old = array_pop($old);

				if ($db->table($table)
						->where('player_name', $name)
						->where($type, $old)
						->inc($type, $val)
						->limit(1)
						->update() === 1) {
					$resolve();
				}
			}
			$reject();
		});
	}

	public function addCurrency(string $name) : PromiseInterface {
		$table = $this->table;
		return $this->newPromise()->then(static function (callable $resolve, callable $reject, DBManager $db) use ($table, $name) : void {
			$cfg = $db->getConfig();
			$dbName = $cfg['connections'][$cfg['default']]['database'];
			$has = !empty($db->query('select ? from information_schema.COLUMNS where TABLE_SCHEMA = ? && COLUMN_NAME = ?;',
				[$table, $dbName, $name]
			));
			if ($has) {
				$reject();
			}
			if ($db->execute(sprintf(
					'alter table %s add column `%s` int not null default 0',
					$table, addslashes($name)
				)) === 0) {
				$resolve();
			}
		});
	}

	public function removeCurrency(string $name) : PromiseInterface {
		$table = $this->table;
		return $this->newPromise()->then(static function (callable $resolve, callable $reject, DBManager $db) use ($table, $name) : void {
			$cfg = $db->getConfig();
			$dbName = $cfg['connections'][$cfg['default']]['database'] ?? null;
			if ($dbName === null) {
				throw new \InvalidArgumentException();
			}
			$notFound = empty($db->query('select ? from information_schema.COLUMNS where TABLE_SCHEMA = ? && COLUMN_NAME = ?;',
				[$table, $dbName, $name]
			));
			if ($notFound) {
				$reject();
			}
			if ($db->execute(sprintf(
					'alter table %s drop column `%s`',
					$table, addslashes($name)
				)) === 0) {
				$resolve();
			}
			$reject();
		});
	}

	public function hasCurrency(string $name) : PromiseInterface {
		$table = $this->table;
		return $this->newPromise()->then(static function (callable $resolve, callable $reject, DBManager $db) use ($table, $name) : void {
			$cfg = $db->getConfig();
			$dbName = $cfg['connections'][$cfg['default']]['database'];
			if (!empty($db->query('select ? from information_schema.COLUMNS where TABLE_SCHEMA=? && COLUMN_NAME=?;',
				[$table, $dbName, $name]
			))) {
				$resolve();
			}
			$reject();
		});
	}

	public function asort(string $type, int $limit) : PromiseInterface {
		return $this->sort('ASC', $limit, $type);
	}

	public function dsort(string $type, int $limit) : PromiseInterface {
		return $this->sort('DESC', $limit, $type);
	}

	private function sort(string $mode, int $limit, string $type) : Promise {
		$table = $this->table;
		return $this->newPromise()->then(static function (callable $resolve, callable $reject, DBManager $db) use ($mode, $limit, $table, $type) : void {
			$resolve($db->table($table)
				->order($type, $mode)
				->limit($limit)
				->select()
				->column($type, 'player_name'));
		});
	}

	private function newPromise() : Promise {
		return (new Promise())->bind(ThinkPHPTask::class);
	}
}