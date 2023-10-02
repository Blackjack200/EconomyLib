<?php

namespace blackjack200\economy\provider\await;

use blackjack200\economy\provider\next\AccountDataProxy;
use blackjack200\economy\provider\next\impl\tools\BidirectionalIndexedDataVisitor;
use blackjack200\economy\provider\next\impl\types\IdentifierProvider;
use libasync\await\Await;
use prokits\player\PracticePlayer;

/**
 * @template T of scalar
 */
abstract class BaseRowData {
	use RowDataCreationTrait;

	private int $max = 1024;


	/**
	 * @param T $default
	 * @param \Closure(T):T $validator
	 */
	final protected function __construct(
		private readonly string   $key,
		private readonly mixed    $default,
		private readonly \Closure $validator,
	) {
		$this->setup();
	}

	/**
	 * @return T
	 */
	public function get(PracticePlayer|string $player) {
		$all = AccountDataProxy::getAll(IdentifierProvider::autoOrName($player));
		/** @var T $fetchedRawData */
		$fetchedRawData = $all[$this->key] ?? $this->default;
		$data = ($this->validator)($fetchedRawData);
		if ($player instanceof PracticePlayer) {
			$this->writeCache($data, $player);
		}
		return $data;
	}

	/**
	 * @return T
	 */
	public function getCached(PracticePlayer|string $player) {
		$data = $this->readCache($player);
		if ($data === null) {
			Await::do(fn() => $this->get($player))->logError();
		}
		return $data ?? $this->default;
	}

	public function refresh(PracticePlayer $player) : void {
		$this->get($player);
	}

	public function reset(PracticePlayer|string $player) : void {
		$this->set($player, $this->default);
	}

	/**
	 * @param T $data
	 */
	public function set(PracticePlayer|string $player, $data) : bool {
		$validatedData = ($this->validator)($data);
		$success = AccountDataProxy::setAuto(IdentifierProvider::autoOrName($player), $this->key, $validatedData);
		if ($player instanceof PracticePlayer && $success) {
			$this->refresh($player);
		}
		return $success;
	}

	public function delete(PracticePlayer|string $player) : bool {
		$success = AccountDataProxy::delete(IdentifierProvider::autoOrName($player), $this->key);
		if ($player instanceof PracticePlayer && $success) {
			$this->refresh($player);
		}
		return $success;
	}

	public function add(PracticePlayer|string $player, int $delta) : bool {
		$success = AccountDataProxy::updateAuto(IdentifierProvider::autoOrName($player), $this->key, static fn($old) => ((int) $old) + $delta);
		if ($player instanceof PracticePlayer && $success) {
			$this->refresh($player);
		}
		return $success;
	}

	/**
	 * @return T|null
	 */
	abstract protected function readCache(PracticePlayer|string $player) : mixed;

	/**
	 * @param T $data
	 */
	abstract protected function writeCache(mixed $data, PracticePlayer|string $player) : void;

	abstract public function clearCache() : void;

	public function asort(int $limit) : BidirectionalIndexedDataVisitor {
		return AccountDataProxy::sort($this->key, $limit, true);
	}

	public function dsort(int $limit) : BidirectionalIndexedDataVisitor {
		return AccountDataProxy::sort($this->key, $limit, false);
	}

	protected function setup() : void { }
}