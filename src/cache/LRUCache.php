<?php

namespace blackjack200\cache;

use blackjack200\cache\ds\BidirectionalNode;

class LRUCache implements CacheInterface {
	/** @var BidirectionalNode[] */
	private array $fastNode = [];
	private BidirectionalNode $head;
	private BidirectionalNode $tail;

	public function __construct(private readonly int $capacity) {
		$this->head = new BidirectionalNode();
		$this->tail = new BidirectionalNode();

		$this->clearAll();
	}

	public function clearAll() : bool {
		$this->head->next = $this->tail;
		$this->head->prev = null;
		$this->tail->next = null;
		$this->tail->prev = $this->head;
		$this->fastNode = [];
		return true;
	}

	public function put(string $key, mixed $val) : bool {
		if (isset($this->fastNode[$key])) {
			$this->fastNode[$key]->value = $val;
			return true;
		}
		$oldHead = $this->head->next;

		$newHead = new BidirectionalNode();
		$newHead->key = $key;
		$newHead->value = $val;

		$newHead->prev = null;
		$newHead->next = $oldHead;
		$oldHead->prev = $newHead;

		$this->head->next = $newHead;
		$this->fastNode[$key] = $newHead;

		if (count($this->fastNode) > $this->capacity) {
			$oldTail = $this->tail->prev;
			unset($this->fastNode[$oldTail->key]);
			$this->tail->prev = $oldTail->prev;
			$this->tail->prev->next = $this->tail;

			$oldTail->prev = null;
			$oldTail->next = null;
		}

		return true;
	}

	public function get(string $key, bool $dirty = true) : mixed {
		$node = $this->fastNode[$key] ?? null;
		if ($node !== null) {
			if ($dirty) {
				$p = $node;

				$pPrev = $p->prev;
				if ($pPrev !== null) {
					$pNext = $p->next;

					$pPrev->next = $pNext;
					$pNext->prev = $pPrev;

					$oldHead = $this->head->next;

					$oldHead->prev = $p;
					$p->next = $oldHead;
					$p->prev = $this->head;

					$this->head->next = $p;
				}
			}
			return $node->value;
		}
		return null;
	}

	public function has(string $key) : bool {
		return isset($this->fastNode[$key]);
	}

	public function clear(string $key) : bool {
		unset($this->fastNode[$key]);
		return true;
	}

	public function keys() : array {
		return array_keys($this->fastNode);
	}

	public function display() : void {
		$next = $this->head;
		echo "head -> ";
		while (($next = ($next->next)) !== null && $next !== $this->tail && $next !== $this->head) {
			echo "$next->key -> ";
		}
		echo "tail\n";
	}
}