<?php

namespace blackjack200\economy\provider\next\impl\tools;

use blackjack200\economy\provider\next\impl\types\Identity;
use blackjack200\economy\provider\next\impl\types\SchemaConstants;
use Generator;

/**
 * @template T of scalar
 */
readonly final class BidirectionalIndexedDataVisitor {
	private function __construct(
		private string $key,
		private array  $data
	) {
	}

	public static function create(string $key, array $arr) : self {
		return new self($key, $arr);
	}

	/**
	 * @return Generator<void,string,T,void>
	 */
	public function indexByName() : Generator {
		foreach ($this->data as $col) {
			if (!isset($col[SchemaConstants::COL_DATA][$this->key])) {
				break;
			}
			yield $col[SchemaConstants::COL_PLAYER_NAME] => $col[SchemaConstants::COL_DATA][$this->key];
		}
	}

	/**
	 * @return Generator<void,string,T,void>
	 */
	public function indexByXuid() : Generator {
		foreach ($this->data as $col) {
			if (!isset($col[SchemaConstants::COL_DATA][$this->key])) {
				break;
			}
			yield $col[SchemaConstants::COL_XUID] => $col[SchemaConstants::COL_DATA][$this->key];
		}
	}

	/**
	 * @return Generator<void,Identity,scalar,void>
	 */
	public function index() : Generator {
		foreach ($this->data as $col) {
			if (!isset($col[$this->key])) {
				break;
			}
			yield Identity::reuse($col[SchemaConstants::COL_PLAYER_NAME], $col[SchemaConstants::COL_XUID]) => $col[SchemaConstants::COL_DATA][$this->key];
		}
	}
}