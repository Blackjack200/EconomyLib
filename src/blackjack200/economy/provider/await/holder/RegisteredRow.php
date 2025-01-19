<?php

namespace blackjack200\economy\provider\await\holder;

use Closure;
use prokits\player\PracticePlayer;

/**
 * @template TValue
 */
class RegisteredRow {

	/** @var null|Closure(PracticePlayer|string $player, TValue|null $oldValue, TValue|null $newValue):void $onUpdate */
	public ?Closure $onUpdate = null;

	public function __construct(
		/** @var Behaviour<?,TValue> */
		public Behaviour $behaviour,
		/** @var TValue $defaultValue */
		public mixed     $defaultValue,
	) {
	}
}