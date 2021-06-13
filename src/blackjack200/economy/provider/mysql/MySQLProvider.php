<?php


namespace blackjack200\economy\provider\mysql;


use blackjack200\economy\provider\ProviderInterface;
use libasync\IPromise;
use libasync\Promise;
use pocketmine\Player;
use think\db\exception\DataNotFoundException;
use think\DbManager;

class MySQLProvider implements ProviderInterface {
	protected string $table;

	public function __construct(string $table) {
		$this->table = $table;
	}

	public function get(string $name, string $type) : IPromise {
		$promise = new Promise();
		$table = $this->table;
		$promise->bind(ThinkPHPTask::class)
			->then(static function (DbManager $db) use ($table, $type, $name) {
				if (!Player::isValidUserName($name)) {
					return false;
				}
				$ret = $db->table($table)->limit(1)
					->where('player_name', $name)
					->column($type);
				return array_pop($ret);
			});
		return $promise;
	}

	public function getALL(string $name) : IPromise {
		$promise = new Promise();
		$table = $this->table;
		$promise->bind(ThinkPHPTask::class)
			->then(static function (DbManager $db) use ($table, $name) {
				if (!Player::isValidUserName($name)) {
					return false;
				}
				return $db->table($table)->limit(1)
					->where('player_name', $name)
					->findOrEmpty();
			});
		return $promise;
	}

	public function set(string $name, string $type, int $val) : IPromise {
		$promise = new Promise();
		$table = $this->table;
		$promise->bind(ThinkPHPTask::class)
			->then(static function (DbManager $db) use ($table, $val, $type, $name) : bool {
				try {
					$db->table($table)->where('player_name', $name)->findOrFail();
					return $db->table($table)->where('player_name', $name)->update([$type => $val]);
				} catch (DataNotFoundException $ex) {
					return (bool) $db->table($table)->insert(
						['player_name' => $name, $type => $val]
					);
				}
			});
		return $promise;
	}

	public function initialize(string $name) : IPromise {
		$promise = new Promise();
		$table = $this->table;
		$promise->bind(ThinkPHPTask::class)
			->then(static function (DbManager $db) use ($table, $name) : bool {
				return $db->table($table)->extra('IGNORE')->insert(
					['player_name' => $name]
				);
			});
		return $promise;
	}

	public function add(string $name, string $type, int $val) : IPromise {
		$promise = new Promise();
		$table = $this->table;
		$promise->bind(ThinkPHPTask::class)
			->then(static function (DbManager $db) use ($table, $val, $type, $name) {
				$retry = 1 << 8;
				while ($retry-- > 0) {
					$old = $db->table($table)
						->where('player_name', $name)->limit(1)
						->column($type);
					if (empty($old)) {
						return false;
					}
					//CAS
					$old = array_pop($old);

					if ($db->table($table)
							->where('player_name', $name)
							->where($type, $old)
							->inc($type, $val)
							->limit(1)
							->update() === 1) {
						return true;
					}
				}
			return false;
		});
		return $promise;
	}

	public function addCurrency(string $name) : IPromise {
		$promise = new Promise();
		$table = $this->table;
		$promise->bind(ThinkPHPTask::class)
			->then(static function (DbManager $db) use ($table, $name) : bool {
				$cfg = $db->getConfig();
				$dbName = $cfg['connections'][$cfg['default']]['database'];
				$has = !empty($db->query('select ? from information_schema.COLUMNS where TABLE_SCHEMA = ? && COLUMN_NAME = ?;',
					[$table, $dbName, $name]
				));
				if ($has) {
					return false;
				}
				return $db->execute(
						sprintf(
							'alter table %s add column `%s` int not null default 0',
							$table, addslashes($name)
						)
					) === 0;
			});
		return $promise;
	}

	public function removeCurrency(string $name) : IPromise {
		$promise = new Promise();
		$table = $this->table;
		$promise->bind(ThinkPHPTask::class)
			->then(static function (DbManager $db) use ($table, $name) : bool {
				$cfg = $db->getConfig();
				$dbName = $cfg['connections'][$cfg['default']]['database'];
				$notFound = empty($db->query('select ? from information_schema.COLUMNS where TABLE_SCHEMA = ? && COLUMN_NAME = ?;',
					[$table, $dbName, $name]
				));
				if ($notFound) {
					return false;
				}
				return $db->execute(
						sprintf(
							'alter table %s drop column `%s`',
							$table, addslashes($name)
						)
					) === 0;
			});
		return $promise;
	}

	public function hasCurrency(string $name) : IPromise {
		$promise = new Promise();
		$table = $this->table;
		$promise->bind(ThinkPHPTask::class)
			->then(static function (DbManager $db) use ($table, $name) : bool {
				$cfg = $db->getConfig();
				$dbName = $cfg['connections'][$cfg['default']]['database'];
				return !empty($db->query('select ? from information_schema.COLUMNS where TABLE_SCHEMA=? && COLUMN_NAME=?;',
					[$table, $dbName, $name]
				));
			});
		return $promise;
	}
}