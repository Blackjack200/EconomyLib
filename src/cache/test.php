<?php

use blackjack200\cache\LRUCache;

require_once __DIR__ . "/CacheInterface.php";
require_once __DIR__ . "/ds/BidirectionalNode.php";
require_once __DIR__ . "/LRUCache.php";

$cache = new LRUCache(3);

$cache->put(1, 'A');
$cache->put(2, 'B');
$cache->put(3, 'C');
$cache->put(4, 'D');
$cache->put(5, 'E');
$cache->display();
var_dump($cache->get(1));
var_dump($cache->get(2));
var_dump($cache->get(3));
var_dump($cache->get(4));
var_dump($cache->get(5));
$cache->display();
var_dump($cache->get(4));
var_dump($cache->get(4));
$cache->display();
var_dump($cache->get(3));
var_dump($cache->get(3));
var_dump($cache->get(3));
$cache->display();
