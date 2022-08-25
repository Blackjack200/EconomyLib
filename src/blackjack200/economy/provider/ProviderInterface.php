<?php


namespace blackjack200\economy\provider;


use libasync\promise\PromiseInterface;

interface ProviderInterface {
	/**
	 * @return PromiseInterface<void>
	 */
	public function initialize(string $name) : PromiseInterface;

	/**
	 * @return \libasync\promise\PromiseInterface<void>
	 */
	public function add(string $name, string $type, int $delta) : PromiseInterface;

	/**
	 * @return PromiseInterface<boolean>
	 */
	public function set(string $name, string $col, int $val) : PromiseInterface;

	/**
	 * @return PromiseInterface<boolean>
	 */
	public function has(string $name) : PromiseInterface;

	/**
	 * @return PromiseInterface<mixed>
	 */
	public function get(string $name, string $type) : PromiseInterface;

	/**
	 * @return PromiseInterface<mixed[]>
	 */
	public function getALL(string $name) : PromiseInterface;

	/**
	 * @return \libasync\promise\PromiseInterface<array>
	 */
	public function asort(string $type, int $limit) : PromiseInterface;

	/**
	 * @return \libasync\promise\PromiseInterface<array>
	 */
	public function dsort(string $type, int $limit) : PromiseInterface;

	/**
	 * @return PromiseInterface<void>
	 */
	public function remove(string $name) : PromiseInterface;

	/**
	 * @return PromiseInterface<void>
	 */
	public function rename(string $old, string $new) : PromiseInterface;

	/**
	 * @return PromiseInterface<void>
	 */
	public function addColumn(string $col, string $type, string $default) : PromiseInterface;

	/**
	 * @return PromiseInterface<boolean>
	 */
	public function hasColumn(string $col) : PromiseInterface;

	/**
	 * @return \libasync\promise\PromiseInterface<string[]>
	 */
	public function getColumns() : PromiseInterface;

	/**
	 * @return PromiseInterface<void>
	 */
	public function removeColumn(string $col) : PromiseInterface;
}