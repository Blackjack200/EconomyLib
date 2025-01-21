<?php

namespace blackjack200\economy\provider\await\column\impl;

use blackjack200\economy\provider\await\column\NumericColumn;
use blackjack200\economy\provider\await\holder\Behaviour;
use blackjack200\economy\provider\await\holder\DataHolder;
use blackjack200\economy\provider\next\AccountDataProxy;
use blackjack200\economy\provider\next\impl\tools\BidirectionalIndexedDataVisitor;
use blackjack200\economy\provider\next\impl\types\Identity;
use Generator;
use prokits\player\PracticePlayer;

class MysqlIntegerColumn extends MysqlColumn implements NumericColumn {
	public function __construct(string $key, mixed $default, Behaviour $behaviour, private bool $signed = false) {
		parent::__construct($key, $default, $behaviour);
	}

	public function isSigned() : bool { return $this->signed; }

	public function add(PracticePlayer|Identity|string $player, int $delta) : Generator|bool {
		return yield from DataHolder::of($player)->numericUpdate($this->key, $delta, $this->signed, false);
	}

	public function dsort(int $limit) : Generator|BidirectionalIndexedDataVisitor {
		return yield from AccountDataProxy::sort($this->key, $limit, false);
	}

	public function asort(int $limit) : Generator|BidirectionalIndexedDataVisitor {
		return yield from AccountDataProxy::sort($this->key, $limit, true);
	}
}