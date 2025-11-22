<?php

namespace blackjack200\cache\ds;

/**
 * @template T
 */
class BidirectionalNode {
	public ?BidirectionalNode $prev;
	public ?BidirectionalNode $next;
	public $key = null;
	/** @var T|null $value */
	public $value = null;

	public function __debugInfo() : ?array {
		return [
			'key' => $this->key,
			'value' => $this->value,
			'next' => $this->next !== null ? $this->next->key : -1,
			'prev' => $this->prev !== null ? $this->prev->key : -1,
		];
	}
}