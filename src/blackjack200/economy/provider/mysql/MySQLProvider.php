<?php


namespace blackjack200\economy\provider\mysql;


use blackjack200\economy\provider\ProviderInterface;
use libasync\IPromise;
use libasync\Promise;
use libasync\Promises;
use pocketmine\Player;
use think\db\exception\DataNotFoundException;
use think\DbManager;

class MySQLProvider implements ProviderInterface {
	public function get(string $name, string $type) : IPromise {
		$promise = new Promise();
		$promise->then(static function (DbManager $db) use ($type, $name) {
			if (!Player::isValidUserName($name)) {
				return false;
			}
			$ret = $db->table('player_info')->limit(1)
				->where('player_name', $name)
				->column($type);
			return array_pop($ret);
		});
		return $promise;
	}

	public function start(IPromise $promise) : void {
		Promises::start($promise, ThinkPHPTask::class);
	}

	public function set(string $name, string $type, int $val) : IPromise {
		$promise = new Promise();
		$promise->then(static function (DbManager $db) use ($val, $type, $name) : bool {
			try {
				$db->table('player_info')->where('player_name', $name)->findOrFail();
				return $db->table('player_info')->where('player_name', $name)->update([$type => $val]);
			} catch (DataNotFoundException $ex) {
				return (bool) $db->table('player_info')->insert(
					['player_name' => $name, $type => $val]
				);
			}
		});
		return $promise;
	}

	public function initialize(string $name) : IPromise {
		$promise = new Promise();
		$promise->then(static function (DbManager $db) use ($name) : bool {
			return $db->table('player_info')->extra('IGNORE')->insert(
				['player_name' => $name]
			);
		});
		return $promise;
	}

	public function add(string $name, string $type, int $val) : IPromise {
		$promise = new Promise();
		$promise->then(static function (DbManager $db) use ($val, $type, $name) {
			$old = $db->table('player_info')
				->where('player_name', $name)->limit(1)
				->column($type);
			if (empty($old)) {
				return false;
			}
			//CAS
			$old = array_pop($old);
			$retry = 16;
			while ($retry-- > 0) {
				if ($db->table('player_info')
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
}