<?php

namespace blackjack200\economy\provider\next\impl;

/**
 * @internal
 */
final class AccountDataHelper {
	private function __construct() { }

	public static function jsonKeyPath(string $key) : string {
		$quotedKey = addslashes(str_replace(<<<'Q'
\
Q
			, <<<'Q'
\\
Q
			, $key));
		$quotedKey = str_replace([<<<'Q'
\"
Q, <<<'Q'
\'
Q], [<<<'Q'
\\"
Q, <<<'Q'
\\'
Q], $quotedKey);
		return "$.\"$quotedKey\"";
	}
}