<?php

namespace blackjack200\economy\provider\await\column;

use blackjack200\economy\provider\next\impl\tools\BidirectionalIndexedDataVisitor;
use prokits\player\PracticePlayer;

interface NumericColumn extends Column {

	public function add(PracticePlayer|string $player, int $delta) : \Generator|bool;

	public function asort(int $limit) : \Generator|BidirectionalIndexedDataVisitor;

	public function dsort(int $limit) : \Generator|BidirectionalIndexedDataVisitor;
}