<?php

namespace blackjack200\economy\provider\await\holder;

use blackjack200\economy\provider\next\impl\types\Identity;
use Closure;
use prokits\player\PracticePlayer;

/**
 * @template TValue
 */
class RegisteredRow {

	/** @var null|Closure(PracticePlayer|Identity $player, TValue|null $oldValue, TValue|null $newValue):void $onUpdate */
	public ?Closure $onUpdate = null;

	public function __construct(
		/** @var Behaviour<?,TValue> */
		public Behaviour $behaviour,
		/** @var TValue $defaultValue */
		public mixed     $defaultValue,
	) {
	}
}