<?php

namespace blackjack200\economy\provider\await;

use blackjack200\economy\provider\mysql\MySQLTypes;
use prokits\player\PracticePlayer;
use WeakMap;

/**
 * @template T of scalar
 */
final class CachedRowData {
	private WeakMap $map;

	/**
	 * @param T $default
	 * @param \Closure(T):T $validator
	 */
	private function __construct(
		private readonly AwaitProviderInterface $provider,
		private readonly string                 $rowName,
		private readonly string                 $type,
		private readonly mixed                  $default,
		private readonly \Closure               $validator,
	) {
		$this->map = new WeakMap();
	}

	public function init() : bool {
		$this->provider->addColumn($this->rowName, $this->type, $this->default);
		return $this->provider->hasColumn($this->rowName);
	}

	/**
	 * @return T
	 */
	public function get(PracticePlayer|string $player) {
		$name = $player instanceof PracticePlayer ? $player->getName() : $player;

		/** @var T $fetchedRawData */
		$fetchedRawData = $this->provider->get($name, $this->rowName) ?? $this->default;
		$data = ($this->validator)($fetchedRawData);
		if ($player instanceof PracticePlayer) {
			$this->map[$player] = $data;
		}
		return $data;
	}

	/**
	 * @return T
	 */
	public function getCached(PracticePlayer|string $player) {
		return $this->map[$player] ?? $this->get($player);
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
		$oldName = $old instanceof PracticePlayer ? $old->getName() : $old;
		$newName = $new instanceof PracticePlayer ? $new->getName() : $new;
		return $this->provider->rename($oldName, $newName);
	}

	/**
	 * @param T $data
	 */
	public function set(PracticePlayer|string $player, $data) : bool {
		$name = $player instanceof PracticePlayer ? $player->getName() : $player;
		$validatedData = ($this->validator)($data);
		$success = $this->provider->set($name, $this->rowName, $validatedData);
		if ($player instanceof PracticePlayer && $success) {
			$this->refresh($player);
		}
		return $success;
	}

	public function asort(int $limit) : array {
		return $this->provider->asort($this->rowName, $limit);
	}

	public function dsort(int $limit) : array {
		return $this->provider->dsort($this->rowName, $limit);
	}

	/**
	 * @return self<bool>
	 */
	public static function bool(
		AwaitProviderInterface $provider,
		string                 $rowName,
		bool                   $default
	) : self {
		return new self(
			$provider,
			$rowName,
			MySQLTypes::BOOLEAN,
			(int) $default,
			static fn($raw) => (((int) $raw) & 1)
		);
	}

	/**
	 * @template NT of (int|float)
	 * @param NT $default
	 * @return self<NT>
	 */
	public static function signed(
		AwaitProviderInterface $provider,
		string                 $rowName,
		string                 $type,
		                       $default
	) : self {
		$int = is_int($default);
		return new self(
			$provider,
			$rowName,
			$type,
			$default,
			static fn($raw) => ($int ? (int) $raw : (float) $raw)
		);
	}

	/**
	 * @template NT of (int|float)
	 * @param NT $default
	 * @return self<NT>
	 */
	public static function unsigned(
		AwaitProviderInterface $provider,
		string                 $rowName,
		string                 $type,
		int|float              $default
	) : self {
		return self::signed(
			$provider,
			$rowName,
			MySQLTypes::unsigned($type),
			$default
		);
	}

	/**
	 * @return self<string>
	 */
	public static function blob(
		AwaitProviderInterface $provider,
		string                 $rowName,
		string                 $length,
		string                 $default
	) : self {
		if (strlen($default) > $length) {
			throw new \InvalidArgumentException('Invalid default string length: ' . strlen($default . ' vs ' . $length));
		}
		return self::signed(
			$provider,
			$rowName,
			MySQLTypes::blob($length),
			$default
		);
	}

	/**
	 * @return self<string>
	 */
	public static function char(
		AwaitProviderInterface $provider,
		string                 $rowName,
		string                 $length,
		string                 $default
	) : self {
		if (strlen($default) > $length) {
			throw new \InvalidArgumentException('Invalid default string length: ' . strlen($default . ' vs ' . $length));
		}
		return self::signed(
			$provider,
			$rowName,
			MySQLTypes::char($length),
			$default
		);
	}

	/**
	 * @return self<string>
	 */
	public static function varchar(
		AwaitProviderInterface $provider,
		string                 $rowName,
		string                 $length,
		string                 $default
	) : self {
		if (strlen($default) > $length) {
			throw new \InvalidArgumentException('Invalid default string length: ' . strlen($default . ' vs ' . $length));
		}
		return self::signed(
			$provider,
			$rowName,
			MySQLTypes::varchar($length),
			$default
		);
	}
}