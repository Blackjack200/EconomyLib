# EconomyLib

A modular player economy system for [PocketMine-MP](https://github.com/pmmp/PocketMine-MP) servers.

Flexible account management, rank handling, and shared data storage with **atomic updates**, caching, and async support.

## 🚀 Pain Points Solved

* 👤 **Player Identity** – Players can freely change names; data stays intact **automatically**.
* ⚡ **Race Conditions** – Atomic updates & numeric deltas prevent corruption.
* 🗄️ **Database Load** – Smart caching & JSON storage minimize queries.
* 🏆 **Rank Management** – Easy assign/remove/update with deadlines.
* 🛠️ **Flexible Data** – Add custom stats/items without schema changes.
* ⏱️ **Async-Friendly** – Smooth async & sync via [libasync](https://github.com/Blackjack200/libasync).

## 🤔 Why Choose This System?

* Optimized for **high-concurrency game servers**.
* Guarantees **data consistency** with **minimal DB overhead**.
* Modular & extendable – add achievements, items, or custom stats easily.
* Modern PHP async-ready using generators.

## 💻 Example Usage

```php
use blackjack200\economy\provider\next\impl\AccountDataService;
use blackjack200\economy\provider\next\impl\types\IdentifierProvider;

// Get player data
$provider = IdentifierProvider::autoOrName($player);
$data = $provider($db, static fn(int $uid) => AccountDataService::getAll($db, $provider));

// Atomic balance update
$provider($db, static fn(int $uid) => AccountDataService::numericDelta($db, $provider, 'balance', 100));
```

## ⚡ Technical Highlights

* **LRU caching** – Fast player lookups, reduced DB load.
* **Atomic numeric delta** – Update balances without full reads.
* **Bidirectional Indexed Visitors** – Efficient top-N stats/rank queries.
* **Transaction-safe operations** – Automatic rollback on conflicts/errors.
* **Generator-based async support** – Fully compatible with high-concurrency servers.

## 💡 Key Concepts

* **Player Identity System** – Consistent, cached UID mapping; offline-first with online updates.
* **Atomic Updates** – Safe concurrent modifications, optimistic/pessimistic caching.
* **Flexible Data Storage** – Arbitrary JSON-serializable keys; bulk reads/writes, delta operations, sorting.
* **Async Proxies** – Generator-based coroutines for smooth sync & async workflows.

## 🏗️ Architecture Overview

**Layers:**

1. **Core Models & Utilities**

    * `Identity` & `IdentifierProvider` unify player identification (XUID + name).
    * Handles online/offline players seamlessly.
    * Built-in hash caching & LRU mechanisms reduce DB access.

2. **Data Services**

    * **AccountDataService** – Atomic key-value data, numeric deltas, bulk operations.
    * **AccountMetadataService** – XUID ↔ Name mapping, consistency guaranteed.
    * **RankService** – Rank registration, assignment, expiry per player.

3. **Proxy Layer**

    * Generator-based async access to services.
    * Abstracts DB transactions & ensures sync/async integration.

4. **Await/Column Layer**

    * Column-level caching and fine-grained data access.
    * Numeric columns, read-only and weak/strong caches.
    * High-performance MySQL implementations.
