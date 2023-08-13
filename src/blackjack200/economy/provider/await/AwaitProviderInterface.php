<?php


namespace blackjack200\economy\provider\await;


interface AwaitProviderInterface {

	public function initialize(string $name) : bool;

	public function add(string $name, string $type, int $delta) : bool;

	public function set(string $name, string $col, $val) : bool;

	public function has(string $name) : bool;

	public function get(string $name, string $type) : mixed;

	public function getALL(string $name) : array;

	public function asort(string $type, int $limit) : array;

	public function dsort(string $type, int $limit) : array;

	public function remove(string $name) : bool;

	public function rename(string $old, string $new) : bool;

	public function addColumn(string $col, string $type, string $default) : bool;

	public function hasColumn(string $col) : bool;

	public function getColumns() : array;

	public function removeColumn(string $col) : bool;

	public function keys() : array;
}