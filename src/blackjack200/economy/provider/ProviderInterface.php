<?php


namespace blackjack200\economy\provider;


use libasync\PromiseInterface;

interface ProviderInterface {
	public function initialize(string $name) : PromiseInterface;

	public function add(string $name, string $type, int $delta) : PromiseInterface;

	public function set(string $name, string $col, int $val) : PromiseInterface;

	public function has(string $name) : PromiseInterface;

	public function get(string $name, string $type) : PromiseInterface;

	public function getALL(string $name) : PromiseInterface;

	public function asort(string $type, int $limit) : PromiseInterface;

	public function dsort(string $type, int $limit) : PromiseInterface;

	public function remove(string $name) : PromiseInterface;

	public function rename(string $old, string $new) : PromiseInterface;

	public function addColumn(string $col, string $type, mixed $default) : PromiseInterface;

	public function hasColumn(string $col) : PromiseInterface;

	public function getColumns() : PromiseInterface;

	public function removeColumn(string $col) : PromiseInterface;
}