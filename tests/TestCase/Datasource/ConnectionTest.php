<?php
declare(strict_types=1);

namespace ElasticKit\Test;

use Cake\Datasource\ConnectionManager;
use Cake\Event\EventDispatcherTrait;
use Cake\Event\EventList;
use Cake\Event\EventManager;
use Cake\Http\Client\ClientEvent;
use Cake\Log\Engine\FileLog;
use Cake\Log\Log;
use Cake\TestSuite\TestCase;
use Elastic\Elasticsearch\ClientInterface;
use ElasticKit\Datasource\Connection;
use ElasticKit\Test\Trait\ElasticClientTrait;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

class ConnectionTest extends TestCase
{
    use ElasticClientTrait;
    use EventDispatcherTrait;

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

    public function testSetCacher(): void
    {
        $connection = ConnectionManager::get('test_elasticsearch');
        $cacher = $this->createMock(CacheInterface::class);
        $connection->setCacher($cacher);
        $this->assertSame($cacher, $connection->getCacher());
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

        Log::setConfig('custom_elasticsearch', [
            'className' => FileLog::class,
            'path' => LOGS,
            'levels' => ['info'],
            'file' => 'info',
        ]);

        $connection->setLogger(null);
        $connection->setConfig('logger', 'custom_elasticsearch');
        $this->assertInstanceOf(FileLog::class, $connection->getLogger());
        Log::drop('custom_elasticsearch');

        Log::setConfig('custom2_elasticsearch', [
            'className' => FileLog::class,
            'path' => LOGS,
            'levels' => ['info'],
            'file' => 'info',
        ]);
        $connection->setLogger(null);
        $connection->setConfig('logger', Log::engine('custom2_elasticsearch'));
        $this->assertInstanceOf(FileLog::class, $connection->getLogger());
        Log::drop('custom2_elasticsearch');
    }

    public function testHttpClientHeaders(): void
    {
        $eventManager = EventManager::instance();
        $eventList = new EventList();
        $eventManager->setEventList($eventList);

        /** @var \ElasticKit\Datasource\Connection $connection */
        $connection = ConnectionManager::get('test_elasticsearch');

        /** @var \Elastic\Elasticsearch\Client $driver */
        $driver = $connection->getDriver();

        $response = $this->createElasticResponse('_document.json');
        $this->mockClientGet(self::ES_HOST . '/my_index/_doc/123', $response);
        $driver->get(['index' => 'my_index', 'id' => 123]);

        $this->assertEventFired('HttpClient.beforeSend');

        /** @var \Cake\Http\Client\ClientEvent $event */
        $event = $eventList[0];
        $this->assertInstanceOf(ClientEvent::class, $event);

        $request = $event->getRequest();
        $this->assertEquals(
            $request->getHeaderLine('Accept'),
            $request->getHeaderLine('Content-Type'),
        );
    }
}
