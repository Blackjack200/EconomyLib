<?php

namespace blackjack200\economy\provider\await\column\impl;

use blackjack200\economy\provider\await\column\NumericColumn;
use blackjack200\economy\provider\next\AccountDataProxy;
use blackjack200\economy\provider\next\impl\tools\BidirectionalIndexedDataVisitor;
use blackjack200\economy\provider\next\impl\types\IdentifierProvider;
use Generator;
use prokits\player\PracticePlayer;

class MysqlIntegerColumn extends MysqlColumn implements NumericColumn {
	public function add(PracticePlayer|string $player, int $delta) : Generator|bool {
		$success = yield from AccountDataProxy::update(IdentifierProvider::autoOrName($player), $this->key, static fn($old) => ((int) $old) + $delta);
		if ($success) {
			yield from $this->syncCache($player);
		}
		return $success;
	}

	public function dsort(int $limit) : Generator|BidirectionalIndexedDataVisitor {
		return yield from AccountDataProxy::sort($this->key, $limit, true);
	}

	public function asort(int $limit) : Generator|BidirectionalIndexedDataVisitor {
		return yield from AccountDataProxy::sort($this->key, $limit, true);
	}
}