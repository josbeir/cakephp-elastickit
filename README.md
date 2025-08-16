# ElasticKit

A lightweight CakePHP 5 plugin for working with Elasticsearch using the official PHP client.

This plugin provides a minimal integration: official Elasticsearch client wired into CakePHP with basic Index classes, ResultSet decoration, and Document entities. You handle persistence and validation.

Note: This plugin does NOT implement RepositoryInterface, keeping the abstraction minimal and focused.

If you want validation and persistence, stop reading and go to [cakephp/elastic-search](https://github.com/cakephp/elastic-search).

## Why this plugin?

- Uses the official Elasticsearch PHP client (`elasticsearch/elasticsearch`) instead of Elastica.
- Fewer surprises across Elasticsearch major releases; you mostly update dependencies when you upgrade.
- Compose queries directly with the client API or with `spatie/elasticsearch-query-builder`.
- Includes a few convenience methods (`get()`, `find()`) and result decoration via a `Document` entity.

**Personal note**: I always felt that the official plugin was holding me back with its heavy abstractions and opinionated approach. While the official plugin does allow direct query building with Elastica's query builder, this plugin gives you the freedom to work directly with Elasticsearch's official client while still providing CakePHP integration conveniences.

## How it differs from the original cakephp/elastic-search plugin

The community plugin at cakephp/elastic-search (Elastica-based) offers an ORM-like experience (types, persisters, validation, and more). This plugin takes a different approach:

- Official client, no Elastica layer.
- Very thin abstraction over the client; no ORM/persistence layer is provided.
- Query building is your choice: use Spatie’s query builder or talk to the client directly.
- Minimal coupling to Elasticsearch internals reduces breakage between major versions. Upgrades are mostly dependency bumps rather than refactors.

If you need a full ORM-style persistence layer, the original plugin may suit you better. If you want a clean, stable way to use the official client inside CakePHP with a bit of extra ergonomics, this plugin is for you.

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

Document entities are resolved automatically from your index name. For an index named `test_items`, the plugin will try `App\Model\Document\TestItem`. If not present, it falls back to the generic `ElasticKit\Document`.

```php
namespace App\Model\Document;

use ElasticKit\Document;

class TestItem extends Document
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

$results = $TestItems->find(function (Builder $q) {
	$q->query(['match' => ['title' => 'cake']])
	  ->size(25)
	  ->sort('_score', 'desc');
});

foreach ($results as $doc) {
	// $doc is an instance of your Document class (or the base one)
	// $doc->id, $doc->score, and _source fields are accessible as properties
}
```

### 2) Directly via the official client

Every unknown method call on your index instance proxies to the underlying `Elastic\Elasticsearch\Client`, so you can use the full API:

```php
$response = $TestItems->search([
	'index' => $TestItems->getIndexName(),
	'body' => [
		'query' => ['match_all' => (object)[]],
		'size' => 10,
	],
]);

// Convert as needed
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

		return $TestItems->find(function ($builder) use ($q) {
			$builder->query(['match' => ['title' => $q]])->size(10);
		});
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

## License

MIT

