<?php

namespace blackjack200\economy\provider\next\impl\types;

class SchemaConstants {
	public const TABLE_ACCOUNT_METADATA = 'account_metadata';
	public const COL_UID = 'uid';
	public const COL_XUID = 'xuid';
	public const COL_LAST_MODIFIED_TIME = 'last_modified_time';
	public const COL_PLAYER_NAME = 'player_name';
	public const COL_DATA = 'data';

	public const TABLE_RANK_REG = 'rank_registry';
	public const COL_RANK_BASENAME = 'basename';
	public const COL_RANK_DISPLAY = 'display';

	public const TABLE_RANK_PLAYER_DATA = 'rank_player_data';
	public const COL_RANK_DEADLINE = 'deadline';
}