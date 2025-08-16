# ElasticKit
[![CI](https://github.com/josbeir/cakephp-elastikit/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/josbeir/cakephp-elastikit/actions/workflows/ci.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg?style=flat)](https://phpstan.org/)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%208.2-8892BF.svg)](https://php.net/)

A lightweight CakePHP 5 plugin for working with Elasticsearch using the official PHP client.

## Design Philosophy

This plugin provides minimal abstraction over the official Elasticsearch client. It handles connection management, index location, and result decoration while leaving persistence and validation to you.

**Not included**: ORM-style persistence, validation, or RepositoryInterface implementation.

**Want full ORM features?** Use [cakephp/elastic-search](https://github.com/cakephp/elastic-search) instead, which provides an ORM similar to CakePHP's database layer.

## Why this plugin?

- **Official client**: Uses `elasticsearch/elasticsearch` instead of Elastica for better compatibility across ES versions
- **Minimal abstraction**: Thin layer over the official client - no CakePHP ORM or persistence logic
- **Flexible querying**: Use Spatie's query builder or the client API directly
- **Upgrade-friendly**: Fewer breaking changes between Elasticsearch major releases

## How it differs from the original cakephp/elastic-search plugin

**cakephp/elastic-search** (Elastica-based) provides a full ORM experience with types, persistence, and validation. **ElasticKit** takes a minimal approach:

- Uses the official Elasticsearch client (no Elastica)
- Thin wrapper with no ORM/persistence layer
- Choose your query method: Spatie's builder or direct client API
- Fewer breaking changes between Elasticsearch versions

Use the original plugin for ORM-style features. Use ElasticKit for clean access to the official client with CakePHP conveniences.

## Requirements

- PHP >= 8.2
- CakePHP 5.x
- Elasticsearch 8/9-compatible cluster

## Installation

Install the dependencies in your Cake app:

```bash
composer require josbeir/cakephp-elastikit
```

Ensure the plugin is loaded (one of):

- In `Application::bootstrap()`:

```php
$this->addPlugin('ElasticKit');
```

- Or via `config/plugins.php` if you use the plugins file.

## Configuration

Register an Elasticsearch connection using the plugin’s connection class. You can do this in `config/bootstrap.php` or in a config file loaded during bootstrap:

```php
use Cake\Datasource\ConnectionManager;
use ElasticKit\Datasource\Connection;

ConnectionManager::setConfig('elasticsearch', [
	'className' => Connection::class,
	'hosts' => [
		// Use your cluster endpoints
		'http://localhost:9200',
	],
	// Optional: any PSR-3 logger name registered with Cake\Log\Log or a LoggerInterface instance
	'logger' => 'elasticsearch',
    // All extra arguments are passed to \Elasticsearch\ClientBuilder
]);
```

By default, indices resolve to the `elasticsearch` connection name. You can override the connection per index class via options if needed.

## Defining an Index

Create an index class in `src/Model/Index`, e.g. `TestItemsIndex`:

```php
namespace App\Model\Index;

use ElasticKit\Index;

class TestItemsIndex extends Index
{
	public function initialize(): void
	{
		// Optional: set index alias/name explicitly; otherwise class name is underscored
		// $this->setIndexName('test_items');

		// Optional: provide settings/mappings used by createIndex()/updateIndex()
		$this->setSettings([
			'number_of_shards' => 1,
			'number_of_replicas' => 0,
		]);

		$this->setMappings([
			'properties' => [
				'title' => ['type' => 'text'],
				'created' => ['type' => 'date'],
			],
		]);
	}
}
```

Document entities are resolved automatically from your index name. For an index named `articles`, the plugin will try `App\Model\Document\Article`. If not present, it falls back to the generic `ElasticKit\Document`.

```php
namespace App\Model\Document;

use ElasticKit\Document;

class Article extends Document
{
	// Add accessors/mutators/virtuals as you like. You own persistence.
}
```

## Querying

You can query in two ergonomic ways.

### 1) With Spatie’s query builder

The `Index::find()` method creates a `Spatie\ElasticsearchQueryBuilder\Builder`, sets the index, executes the search, and returns a `ResultSet` that yields `Document` instances.

```php
use Spatie\ElasticsearchQueryBuilder\Builder;

// From a controller/service using IndexLocatorAwareTrait
/** @var \App\Model\Index\TestItemsIndex $TestItems */
$TestItems = $this->fetchIndex('TestItems');

$results = $TestItems->find(function (Builder $builder) {
	return $builder->size(25)
	  ->sort('_score', 'desc');
});

foreach ($results as $doc) {
	// $doc is an instance of your Document class (or the base one)
	// $doc->id, $doc->score, and _source fields are accessible as properties
}
```

### 2) Directly via the official client
Every unknown method call on your index instance proxies to the underlying `Elastic\Elasticsearch\Client`, so you can use the full API. Note that while `get()` is reserved by the Index class for fetching single documents, you can still access the client's `get()` method via `getClient()->get()`:

```php
// Index's get() - returns a Document|null
$doc = $TestItems->get('document_id');

// Client's get() - returns raw Elasticsearch response
$response = $TestItems->getClient()->get([
	'index' => $TestItems->getIndexName(),
	'id' => 'document_id'
]);

$response = $TestItems->search([
	'index' => $TestItems->getIndexName(),
	'body' => [
		'query' => ['match_all' => ...],
		'size' => 10,
	],
]);

// Convert as needed
$resultset = $TestItems->resultSet($response);
$array = $response->asArray();
$object = $response->asObject();
$ok = $response->asBool();
```

### Convenience methods

- `Index::get($id)`: Fetch a single document by id as a `Document|null`.
- `Index::resultSet($response)`: Wrap any Elasticsearch response in the plugin’s `ResultSet`.

The `ResultSet` exposes helpers like:

- `getTook()`, `getMaxScore()`, `getShards()`, `getHitsTotal()`
- Iterates documents, handles both search and bulk-style responses.

## ResultSet API

`ElasticKit\ResultSet` is an iterator over decorated `Document` instances and provides a few helpers around the Elasticsearch response.

- `getTook(): ?int` — The time (ms) the search took (if present).
- `getMaxScore(): ?float` — Max score for hits (if present).
- `getShards(): ?array` — Shard info from the response (if present).
- `getHitsTotal(): ?int` — Reported total hits value (may be `null` depending on ES settings like `track_total_hits`).
- `hasErrors(): bool` — Indicates whether the underlying response reported errors (useful for bulk responses).
- `getResponse(): ResponseInterface` — Access the raw Elasticsearch response object.

Iteration

```php
foreach ($results as $doc) {
	// $doc is \App\Model\Document\YourEntity (if present) or the base Document
}
```

Force a specific document class

```php
use App\Model\Document\TestItem;

$results->setDocumentClass(TestItem::class);
```

Note: ResultSet also includes common collection utilities via Cake’s CollectionTrait (e.g., `map()`, `filter()`, `toList()`), which can be handy for quick transformations.

## CLI: manage indices

The plugin ships a small command to create/update/delete indices based on your index class config (`settings`/`mappings`).

```bash
bin/cake elasticsearch index test_items --create  # create if missing
bin/cake elasticsearch index test_items --update  # put mapping
bin/cake elasticsearch index test_items --delete  # delete index

# Use a plugin or FQCN-style name if needed
bin/cake elasticsearch index App.TestItems --create
```

Use `-v` to print current settings/mappings of the target index.

## Accessing indices from your code

Use the `IndexLocatorAwareTrait` to fetch indices by alias (class name without the `Index` suffix):

```php
use ElasticKit\Locator\IndexLocatorAwareTrait;

class TestItemsService
{
	use IndexLocatorAwareTrait;

	public function searchByTitle(string $q)
	{
		$TestItems = $this->fetchIndex('TestItems');

		return $TestItems->get(1234);
	}
}
```

## Logging and debugging

- Pass a PSR-3 logger (or the name of a Cake log engine) to the connection config via `logger` to capture client requests.
- Convert responses with `->asArray()` or `->asObject()` while developing.

### DebugKit panel

This plugin includes a DebugKit panel that displays Elasticsearch requests per configured connection.

- Ensure `cakephp/debug_kit` is installed and enabled in development.
- The panel appears as “Elasticsearch” in the DebugKit toolbar.
- It hooks into `ElasticKit\Datasource\Connection` loggers at runtime and shows:
	- Count of requests per connection
	- Message and a prettified JSON body

To enable the panel, add it to your DebugKit configuration:

```php
Configure::write('DebugKit.panels', ['ElasticKit.Elasticsearch']);
```

## What this plugin does NOT do

- No ORM-like persistence (no `save()`, no automatic validation). You decide how to index/update/delete documents.
- No type mapping or schema inference. Provide your own index settings/mappings.
- No custom query DSL layer beyond the optional Spatie builder helper.

This is by design to keep the integration thin, explicit, and resilient to upstream changes.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request. For major changes, please open an issue first to discuss what you would like to change.

### Development Setup

1. Clone the repository
2. Install dependencies: `composer install`
3. Run tests: `composer test`

Please make sure to update tests as appropriate and follow the existing code style.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE.md) file for details.
