<?php
declare(strict_types=1);

namespace ElasticKit\Test;

use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use Elastic\Elasticsearch\ClientInterface;
use ElasticKit\Datasource\Connection;
use ElasticKit\Test\Trait\ElasticClientTrait;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

class ConnectionTest extends TestCase
{
    use ElasticClientTrait;

    protected function setUp(): void
    {
        $this->initializeElasticClient();
        parent::setUp();
    }

    public function testConstructor(): void
    {
        $connection = new Connection([
            'name' => 'test_elasticsearch',
        ]);

        $this->assertInstanceOf(Connection::class, $connection);
        $this->assertArrayHasKey('name', $connection->config());
    }

    public function testInstanceCreation(): void
    {
        $connection = ConnectionManager::get('test_elasticsearch');
        $this->assertInstanceOf(Connection::class, $connection);
        $this->assertInstanceOf(ClientInterface::class, $connection->getDriver());
    }

    public function testGetCacher(): void
    {
        $connection = ConnectionManager::get('test_elasticsearch');
        $cacher = $connection->getCacher();
        $this->assertInstanceOf(CacheInterface::class, $cacher);
    }

    public function testConfigName(): void
    {
        $connection = ConnectionManager::get('test_elasticsearch');
        $this->assertEquals('test_elasticsearch', $connection->configName());
    }

    public function testConfig(): void
    {
        $connection = ConnectionManager::get('test_elasticsearch');
        $config = $connection->config();

        $this->assertNotEmpty($config);
        $this->assertArrayHasKey('name', $config);
        $this->assertArrayHasKey('hosts', $config);
        $this->assertArrayHasKey('logger', $config);
        $this->assertArrayHasKey('httpClient', $config);
    }

    public function testGetLogger(): void
    {
        /** @var \ElasticKit\Datasource\Connection $connection */
        $connection = ConnectionManager::get('test_elasticsearch');
        $logger = $connection->getLogger();
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testSetLogger(): void
    {
        /** @var \ElasticKit\Datasource\Connection $connection */
        $connection = ConnectionManager::get('test_elasticsearch');
        $logger = $this->createMock(LoggerInterface::class);
        $connection->setLogger($logger);
        $this->assertSame($logger, $connection->getLogger());
    }
}
