<?php


namespace blackjack200\economy\provider\await;


use libasync\promise\PromiseInterface;

interface AwaitProviderInterface {

	public function initialize(string $name) : \Generator;

	public function add(string $name, string $type, int $delta) : \Generator;

	public function set(string $name, string $col, $val) : \Generator;

	public function has(string $name) : \Generator;

	public function get(string $name, string $type) : \Generator;

	public function getALL(string $name) : \Generator;

	public function asort(string $type, int $limit) : \Generator;

	public function dsort(string $type, int $limit) : \Generator;

	public function remove(string $name) : \Generator;

	public function rename(string $old, string $new) : \Generator;

	public function addColumn(string $col, string $type, string $default) : \Generator;

	public function hasColumn(string $col) : \Generator;

	public function getColumns() : \Generator;

	public function removeColumn(string $col) : \Generator;

	public function keys() : \Generator;
}