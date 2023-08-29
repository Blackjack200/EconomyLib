<?php

namespace blackjack200\economy\provider\await;

use libasync\await\Await;
use prokits\player\PracticePlayer;

/**
 * @template T of scalar
 */
abstract class BaseRowData {
	use RowDataCreationTrait;

	private int $max = 1024;
	private bool $available = false;


	/**
	 * @param T $default
	 * @param \Closure(T):T $validator
	 */
	final protected function __construct(
		private readonly AwaitProviderInterface $provider,
		private readonly string                 $rowName,
		private readonly string                 $type,
		private readonly mixed                  $default,
		private readonly \Closure               $validator,
	) {
		$this->setup();
		Await::do(function() : void {
			$this->init();
			$this->available = true;
		})->panic();
	}

	public function init() : bool {
		$this->provider->addColumn($this->rowName, $this->type, $this->default);
		return $this->provider->hasColumn($this->rowName);
	}

	/**
	 * @return T
	 */
	public function get(PracticePlayer|string $player) {
		$this->waitForReady();
		$name = $player instanceof PracticePlayer ? $player->getName() : $player;

		/** @var T $fetchedRawData */
		$fetchedRawData = $this->provider->get($name, $this->rowName) ?? $this->default;
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

	public function rename(
		PracticePlayer|string $old,
		PracticePlayer|string $new,
	) : bool {
		$this->waitForReady();
		$oldName = $old instanceof PracticePlayer ? $old->getName() : $old;
		$newName = $new instanceof PracticePlayer ? $new->getName() : $new;
		return $this->provider->rename($oldName, $newName);
	}

	/**
	 * @param T $data
	 */
	public function set(PracticePlayer|string $player, $data) : bool {
		$this->waitForReady();
		$name = $player instanceof PracticePlayer ? $player->getName() : $player;
		$validatedData = ($this->validator)($data);
		$success = $this->provider->set($name, $this->rowName, $validatedData);
		if ($player instanceof PracticePlayer && $success) {
			$this->refresh($player);
		}
		return $success;
	}

	public function add(PracticePlayer|string $player, int $delta) : bool {
		$this->waitForReady();
		$name = $player instanceof PracticePlayer ? $player->getName() : $player;
		$success = $this->provider->add($name, $this->rowName, $delta);
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

	public function asort(int $limit) : array {
		$this->waitForReady();
		return $this->provider->asort($this->rowName, $limit);
	}

	public function dsort(int $limit) : array {
		$this->waitForReady();
		return $this->provider->dsort($this->rowName, $limit);
	}

	protected function setup() : void { }

	private function waitForReady() : void {
		$counter = 1 << 20;
		while (!$this->available && $counter-- > 0) {
			Await::usleep(20);
		}
		if (!$this->available) {
			throw new \RuntimeException("RowData $this->rowName unavailable");
		}
	}
}