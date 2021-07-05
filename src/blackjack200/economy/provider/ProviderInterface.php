<?php


namespace blackjack200\economy\provider;


use libasync\PromiseInterface;

interface ProviderInterface {
	public function initialize(string $name) : PromiseInterface;

	public function get(string $name, string $type) : PromiseInterface;

	public function getALL(string $name) : PromiseInterface;

	public function set(string $name, string $type, int $val) : PromiseInterface;

	public function add(string $name, string $type, int $val) : PromiseInterface;

	public function asort(string $type, int $limit) : PromiseInterface;

	public function dsort(string $type, int $limit) : PromiseInterface;

	public function addCurrency(string $name) : PromiseInterface;

	public function removeCurrency(string $name) : PromiseInterface;

	public function hasCurrency(string $name) : PromiseInterface;
}