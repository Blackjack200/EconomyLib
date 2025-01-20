<?php

namespace blackjack200\economy\provider\next\impl\tools;

use blackjack200\economy\provider\next\impl\types\Identity;
use blackjack200\economy\provider\next\impl\types\SchemaConstants;
use Generator;

readonly final class IndexedVisitor {
	private function __construct(
		private string $key,
		private array  $data
	) {
	}

	public static function create(string $key, array $arr) : self {
		return new self($key, $arr);
	}

	/**
	 * @return Generator<void,string,scalar,void>
	 */
	public function indexByName() : Generator {
		foreach ($this->data as $col) {
			if (!isset($col[$this->key])) {
				break;
			}
			yield $col[SchemaConstants::COL_PLAYER_NAME] => $col[$this->key];
		}
	}

	/**
	 * @return Generator<void,string,scalar,void>
	 */
	public function indexByXuid() : Generator {
		foreach ($this->data as $col) {
			if (!isset($col[$this->key])) {
				break;
			}
			yield $col[SchemaConstants::COL_XUID] => $col[$this->key];
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
			yield Identity::reuse($col[SchemaConstants::COL_PLAYER_NAME], $col[SchemaConstants::COL_XUID]) => $col[$this->key];
		}
	}
}