# ElasticKit - a CakePHP Elasticsearch plugin 
[![CI](https://github.com/josbeir/cakephp-elastikit/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/josbeir/cakephp-elastikit/actions/workflows/ci.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg?style=flat)](https://phpstan.org/)
[![codecov](https://codecov.io/github/josbeir/cakephp-elastickit/graph/badge.svg?token=4VGWJQTWH5)](https://codecov.io/github/josbeir/cakephp-elastickit)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%208.2-8892BF.svg)](https://php.net/)
[![Packagist Downloads](https://img.shields.io/packagist/dt/josbeir/cakephp-elastickit)](https://packagist.org/packages/josbeir/cakephp-elastickit)
![GitHub License](https://img.shields.io/github/license/josbeir/cakephp-elastickit)

A lightweight CakePHP 5 plugin for working with Elasticsearch using the [official PHP client](https://github.com/elastic/elasticsearch-php).

## Table of Contents

- [Design Philosophy](#design-philosophy)
- [Why this plugin?](#why-this-plugin)
- [How it differs from the original cakephp/elastic-search plugin](#how-it-differs-from-the-original-cakephpelastic-search-plugin)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Defining an Index](#defining-an-index)
- [Document Entities](#document-entities)
- [Querying](#querying)
- [ResultSet API](#resultset-api)
- [CLI: manage indices](#cli-manage-indices)
- [Accessing indices from your code](#accessing-indices-from-your-code)
- [Logging and debugging](#logging-and-debugging)
- [What this plugin does NOT do](#what-this-plugin-does-not-do)
- [Contributing](#contributing)
- [License](#license)

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
- CakePHP >= 5.2
- Elasticsearch >= 8.5 / 9.x

> **Note**: Lock the `elasticsearch/elasticsearch` package version in your composer file to match your Elasticsearch server version for optimal compatibility:

## Installation

Install the dependencies in your Cake app:

```bash
composer require josbeir/cakephp-elastickit
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


### HTTP Client Configuration

By default, the connection uses CakePHP's Http Client, which makes it easy to mock requests during testing. You can override this behavior using the `httpClient` option:

```php
use Cake\Datasource\ConnectionManager;
use ElasticKit\Datasource\Connection;

ConnectionManager::setConfig('elasticsearch', [
	'className' => Connection::class,
	'hosts' => ['http://localhost:9200'],
	
	// Optional: Use a different HTTP client
	// Default: Uses CakePHP's Http Client (great for testing/mocking, hijacking the request/response)
	// 'httpClient' => new YourPsr18HttpClient(), // Any PSR-18 compatible client
	// 'httpClient' => new Elastic\Transport\Client\Curl, // Use the default cURL client from elasticsearch/elasticsearch
]);
```

## Defining an Index

Create an index class in `src/Model/Index`, e.g. `ArticlesIndex`:

```php
namespace App\Model\Index;

use ElasticKit\Index;

class ArticlesIndex extends Index
{
	public function initialize(): void
	{
		// Optional: set index alias/name explicitly; otherwise class name is underscored
		// $this->setIndexName('articles');

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

## Document Entities

Document entities are resolved automatically from your index name. For an index named `articles`, the plugin will try `App\Model\Document\Article`. If not present, it falls back to the generic `ElasticKit\Document`.

```php
namespace App\Model\Document;

use ElasticKit\Document;

class Article extends Document
{
	// Add accessors/mutators/virtuals as you like.
}
```

Document entities follow CakePHP's EntityInterface with one key difference: the document ID and score are stored in a reserved property to avoid field name collisions.

Access these properties using:

```php
$doc = $Articles->get('some-id');
$id = $doc->getDocumentId();     // Gets the Elasticsearch document ID
$score = $doc->getScore();       // Gets the search score (if from a search result)
```

## Querying

You can query in two ergonomic ways.

### 1) With [Spatie’s](https://github.com/spatie/elasticsearch-query-builder) query builder

The `Index::find()` method creates a `Spatie\ElasticsearchQueryBuilder\Builder`, sets the index, executes the search, and returns a `ResultSet` that yields `Document` instances.

```php
use Spatie\ElasticsearchQueryBuilder\Builder;
use Spatie\ElasticsearchQueryBuilder\Aggregations\MaxAggregation;
use Spatie\ElasticsearchQueryBuilder\Queries\MatchQuery;

$Articles = $this->fetchIndex('Articles');

$results = $Articles->find(function (Builder $builder) {
	return $builder
		->addQuery(MatchQuery::create('name', 'elastickit', fuzziness: 3))
		->addAggregation(MaxAggregation::create('score'))
});
```

### 2) Directly via the official client
Every unknown method call on your index instance proxies to the underlying `Elastic\Elasticsearch\Client`, so you can use the full API. Note that while `get()` is reserved by the Index class for fetching single documents, you can still access the client's `get()` method via `getClient()->get()`:

```php
// Index's get() - returns a Document|null
$doc = $Articles->get('document_id');

// Client's get() - returns raw Elasticsearch response
$response = $Articles->getClient()->get([
	'index' => $Articles->getIndexName(),
	'id' => 'document_id'
]);

$response = $Articles->search([
	'index' => $Articles->getIndexName(),
	'body' => [
		'query' => ['match_all' => ...],
		'size' => 10,
	],
]);

// Convert as needed
$resultset = $Articles->resultSet($response);
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
- `getAggregations(): ?array` — Aggregation results from the response (if present).
- `hasErrors(): bool` — Indicates whether the underlying response reported errors (useful for bulk responses).
- `getResponse(): ResponseInterface` — Access the raw Elasticsearch response object.
- `getBuilder(): Builder` — The builder instance.

Iteration

```php
foreach ($results as $doc) {
	// $doc is \App\Model\Document\YourEntity (if present) or the base Document
}
```

Force a specific document class

```php
use App\Model\Document\Article;

$results->setDocumentClass(Article::class);
```

Note: ResultSet also includes common collection utilities via Cake’s CollectionTrait (e.g., `map()`, `filter()`, `toList()`), which can be handy for quick transformations.

## CLI: manage indices

The plugin ships a small command to create/update/delete indices based on your index class config (`settings`/`mappings`).

```bash
bin/cake elasticsearch index articles --create  # create if missing
bin/cake elasticsearch index articles --update  # put mapping
bin/cake elasticsearch index articles --delete  # delete index

# Use a plugin or FQCN-style name if needed
bin/cake elasticsearch index App.Articles --create
```

Use `-v` to print current settings/mappings of the target index.

## Accessing indices from your code

Use the `IndexLocatorAwareTrait` to fetch indices by alias (class name without the `Index` suffix):

```php
use ElasticKit\Locator\IndexLocatorAwareTrait;

class ArticlesService
{
	use IndexLocatorAwareTrait;

	public function searchByTitle(string $q)
	{
		$Articles = $this->fetchIndex('Articles');

		return $Articles->get(1234);
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
