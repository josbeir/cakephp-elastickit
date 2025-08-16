<?php
declare(strict_types=1);

namespace ElasticKit\Datasource;

use Cake\Cache\Cache;
use Cake\Core\InstanceConfigTrait;
use Cake\Datasource\ConnectionInterface;
use Cake\Http\Client;
use Cake\Log\Log;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;

class Connection implements ConnectionInterface
{
    use InstanceConfigTrait;

    /**
     * The ElasticKit client instance
     */
    protected ClientInterface $client;

    /**
     * The cache instance for storing index metadata.
     */
    protected ?CacheInterface $cacher = null;

    /**
     * The name of the configuration for this connection.
     */
    protected string $configName;

    /**
     * The logger instance for this connection.
     */
    protected ?LoggerInterface $logger = null;

    /**
     * Default configuration for the index.
     */
    protected array $_defaultConfig = [
        'name' => null,
        'hosts' => [],
        'logger' => null,
        'httpClient' => null,
    ];

    /**
     * Constructor.
     *
     * @param array $config Configuration options.
     */
    public function __construct(array $config = [])
    {
        $this->setConfig($config);
        $this->configName = $this->getConfig('name', 'elasticsearch');
        $this->initialize();
    }

    /**
     * Initialize the ElasticKit client instance.
     *
     * Instead of doing this in the constructor, we do it here to allow
     * runtime configuration changes (like changing the logger).
     */
    public function initialize(): void
    {
        $config = $this->getConfig();

        $config['httpClient'] = $this->getConfig('httpClient', new Client());
        $config['logger'] = $this->getLogger();

        unset($config['name']);

        $this->client = ClientBuilder::fromConfig(array_filter($config));
    }

    /**
     * @inheritDoc
     */
    public function getDriver(string $role = self::ROLE_WRITE): ClientInterface
    {
        return $this->client;
    }

    /**
     * @inheritDoc
     */
    public function setCacher(CacheInterface $cacher): self
    {
        $this->cacher = $cacher;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getCacher(): CacheInterface
    {
        if ($this->cacher instanceof CacheInterface) {
            return $this->cacher;
        }

        $configName = $this->getConfig('cacheMetadata', '_cake_model_');
        if (!is_string($configName)) {
            $configName = '_cake_model_';
        }

        if (!class_exists(Cache::class)) {
            throw new RuntimeException(
                'To use caching you must either set a cacher using Connection::setCacher()' .
                ' or require the cakephp/cache package in your composer config.',
            );
        }

        return $this->cacher = Cache::pool($configName);
    }

    /**
     * @inheritDoc
     */
    public function configName(): string
    {
        return $this->configName;
    }

    /**
     * @inheritDoc
     */
    public function config(): array
    {
        return $this->getConfig();
    }

    /**
     * Get the logger instance.
     */
    public function getLogger(): ?LoggerInterface
    {
        if ($this->logger instanceof LoggerInterface) {
            return $this->logger;
        }

        $logger = $this->getConfig('logger');
        if ($logger instanceof LoggerInterface) {
            $this->logger = $logger;
        } elseif ($logger !== null) {
            $this->logger = Log::engine($logger);
        } else {
            $this->logger = new NullLogger();
        }

        return $this->logger;
    }

    /**
     * Set the logger instance.
     *
     * @param \Psr\Log\LoggerInterface $logger The logger instance to set.
     */
    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }
}
