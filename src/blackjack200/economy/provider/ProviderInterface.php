<?php


namespace blackjack200\economy\provider;


use libasync\IPromise;

interface ProviderInterface {
	public function initialize(string $name) : IPromise;

	public function get(string $name, string $type) : IPromise;

	public function getALL(string $name) : IPromise;

	public function set(string $name, string $type, int $val) : IPromise;

	public function add(string $name, string $type, int $val) : IPromise;

	public function addCurrency(string $name) : IPromise;

	public function removeCurrency(string $name) : IPromise;

	public function hasCurrency(string $name) : IPromise;
}