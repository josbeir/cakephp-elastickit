<?php
declare(strict_types=1);

namespace ElasticKit;

use Cake\Core\Exception\CakeException;
use Cake\Core\InstanceConfigTrait;
use Cake\Datasource\ConnectionManager;
use Cake\Utility\Inflector;
use Closure;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Endpoints\AbstractEndpoint;
use Elastic\Elasticsearch\Response\Elasticsearch;
use ElasticKit\Datasource\Connection;
use RuntimeException;
use Spatie\ElasticsearchQueryBuilder\Builder;
use function Cake\Core\namespaceSplit;

/**
 * Base class for ElasticKit indices.
 *
 * @mixin \Elastic\Elasticsearch\Client
 */
class Index
{
    use InstanceConfigTrait;

    protected Connection $Connection;

    protected ?string $indexName = null;

    protected array $settings = [];

    protected array $mappings = [];

    /**
     * Default configuration for the index.
     */
    protected array $_defaultConfig = [
        'connection_name' => null,
        'settings' => [],
        'mappings' => [],
    ];

    /**
     * AbstractIndex constructor.
     *
     * @throws \RuntimeException If the Elasticsearch connection is not configured properly.
     */
    public function __construct(array $config = [])
    {
        $this->setConfig($config);

        $connectionName = $this->getConfig('connection_name', static::defaultConnectionName());
        $connection = ConnectionManager::get($connectionName);
        if (!$connection instanceof Connection) {
            throw new RuntimeException('Elasticsearch connection is not configured properly.');
        }

        $this->Connection = $connection;
        $this->initialize();
    }

    /**
     * Initialize the index.
     *
     * This method can be overridden by subclasses to perform additional initialization logic.
     */
    public function initialize(): void
    {
    }

    /**
     * Get the default connection name.
     *
     * @return string The name of the default connection.
     */
    public static function defaultConnectionName(): string
    {
        return 'elasticsearch';
    }

    /**
     * Get the connection instance.
     */
    public function getConnection(): Connection
    {
        return $this->Connection;
    }

    /**
     * Get the ElasticKit client instance.
     */
    public function getClient(): Client
    {
        //@phpstan-ignore return.type
        return $this->getConnection()->getDriver();
    }

    /**
     * Call the ElasticKit client methods dynamically.
     */
    public function __call(string $name, array $arguments): AbstractEndpoint|Elasticsearch
    {
        return $this->getClient()->{$name}(...$arguments);
    }

    /**
     * Convenience method for Client::get().
     */
    public function get(mixed $document_id): ?Document
    {
        $response = $this->getClient()->get([
            'index' => $this->getIndexName(),
            'id' => $document_id,
        ]);

        return $this->resultSet($response)->first();
    }

    /**
     * Build and execute a query using the spatie/elasticsearch-query-builder package.
     *
     * @param \Closure|null $callback A callback function to customize the query builder.
     * @see https://github.com/spatie/ElasticKit-query-builder
     * @throws \RuntimeException If the ElasticKit client is not available.
     */
    public function find(?Closure $callback = null): ResultSet
    {
        $client = $this->getClient();
        if (!class_exists(Builder::class)) {
            throw new RuntimeException(
                'The spatie/elasticsearch-query-builder package is required.
                Please install it via Composer.',
            );
        }

        $builder = new Builder($client);
        if (is_callable($callback)) {
            $callback($builder);
        }

        $response = $builder
            ->index($this->getIndexName())
            ->search();

        return $this->resultSet($response);
    }

    /**
     * Set the index name.
     */
    public function setIndexName(string $indexName): self
    {
        $this->indexName = $indexName;

        return $this;
    }

    /**
     * Get the index name.
     *
     * If the index name is not set, it will be derived from the class name.
     *
     * @throws \Cake\Core\Exception\CakeException If the index name cannot be determined.
     */
    public function getIndexName(): string
    {
        if ($this->indexName === null) {
            $index = namespaceSplit(static::class);
            $index = substr(end($index), 0, -5);
            if ($index === '' || $index === '0') {
                throw new CakeException(
                    'You must specify either the `alias` or the `index` option for the constructor.',
                );
            }

            $this->indexName = Inflector::underscore($index);
        }

        return $this->indexName;
    }

    /**
     * Create the index with the specified settings and mappings.
     *
     * This method should be implemented by subclasses to define the specific settings and mappings.
     */
    public function createIndex(): bool
    {
        return $this->getClient()
            ->indices()
            ->create([
                'index' => $this->getIndexName(),
                'body' => [
                    'settings' => $this->getConfig('settings', []),
                    'mappings' => $this->getConfig('mappings', []),
                ],
            ])->asBool();
    }

    /**
     * Update the index mappings.
     *
     * This method should be implemented by subclasses to define the specific mappings.
     */
    public function updateIndex(): bool
    {
        return $this->getClient()
            ->indices()
            ->putMapping([
                'index' => $this->getIndexName(),
                'body' => $this->getConfig('mappings', []),
            ])
            ->asBool();
    }

    /**
     * Delete the index.
     */
    public function deleteIndex(): bool
    {
        return $this->getClient()
            ->indices()
            ->delete(['index' => $this->getIndexName()])
            ->asBool();
    }

    /**
     * Check if the index exists.
     */
    public function indexExists(): bool
    {
        return $this->getClient()
            ->indices()
            ->exists(['index' => $this->getIndexName()])
            ->asBool();
    }

    /**
     * Set the index settings.
     *
     * @param array $settings The settings to apply to the index.
     */
    public function setSettings(array $settings): self
    {
        $this->setConfig('settings', $settings, false);

        return $this;
    }

    /**
     * Set the index mappings.
     *
     * @param array $mappings The mappings to apply to the index.
     */
    public function setMappings(array $mappings): self
    {
        $this->setConfig('mappings', $mappings, false);

        return $this;
    }

    /**
     * Return a decorated ResultSet instance.
     *
     * @param \Elastic\Elasticsearch\Response\Elasticsearch $response The Elasticsearch response.
     */
    public function resultSet(Elasticsearch $response): ResultSet
    {
        return new ResultSet(
            $response,
            $this->getIndexName(),
        );
    }
}
