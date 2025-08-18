<?php
declare(strict_types=1);

namespace ElasticKit\Test;

use Cake\Datasource\ConnectionManager;
use Cake\Http\Client;
use Cake\Http\TestSuite\HttpClientTrait;
use Cake\TestSuite\TestCase;
use Elastic\Elasticsearch\ClientInterface;
use Elastic\Elasticsearch\Endpoints\Indices;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use ElasticKit\Datasource\Connection;
use ElasticKit\Document;
use ElasticKit\ResultSet;
use ElasticKit\Test\Trait\ElasticClientTrait;
use ElasticKit\TestApp\Model\Index\TestItemsIndex;
use RuntimeException;
use Spatie\ElasticsearchQueryBuilder\Builder;

class IndexTest extends TestCase
{
    use HttpClientTrait;
    use ElasticClientTrait;

    protected TestItemsIndex $Index;

    protected function setUp(): void
    {
        parent::setUp();

        $this->initializeElasticClient();
        $this->Index = new TestItemsIndex();
    }

    protected function tearDown(): void
    {
        unset($this->Index);
        parent::tearDown();
    }

    public function testInitialize(): void
    {
        $this->Index->initialize();
        $this->assertInstanceOf(TestItemsIndex::class, $this->Index);
    }

    public function testGetConnetion(): void
    {
        $connection = $this->Index->getConnection();
        $this->assertInstanceOf(Connection::class, $connection);
        $this->assertEquals('test_elasticsearch', $connection->getConfig('name'));
    }

    public function testInvalidConnection()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Elasticsearch connection is not configured properly.');

        ConnectionManager::setConfig('not_elasticsearch', [
            'url' => 'sqlite:///:memory',
            'cacheMetadata' => false,
        ]);

        new TestItemsIndex([
            'connection_name' => 'not_elasticsearch',
        ]);
    }

    public function testGetIndexName(): void
    {
        $this->assertNotEmpty($this->Index->getIndexName());
        $this->assertEquals('test_items', $this->Index->getIndexName());
    }

    public function testSetIndexName(): void
    {
        $this->Index->setIndexName('test_index');
        $this->assertEquals('test_index', $this->Index->getIndexName());
    }

    public function testGetClient(): void
    {
        $client = $this->Index->getClient();
        $this->assertInstanceOf(ClientInterface::class, $client);
    }

    public function testFind(): void
    {
        $response = $this->createElasticResponse('_search.json');
        $this->mockClientGet(self::ES_HOST . '/test_items/_search', $response);

        $resultset = $this->Index->find();
        $this->assertInstanceOf(ResultSet::class, $resultset);

        // Check if closure has the right argument and is called.
        $called = false;
        $resultset = $this->Index->find(function ($builder) use (&$called): Builder {
            $called = true;
            $this->assertInstanceOf(Builder::class, $builder);

            return $builder;
        });

        $this->assertTrue($called, 'The finder closure was not invoked.');
        $this->assertInstanceOf(ResultSet::class, $resultset);
    }

    public function testInvalidBuilderResult(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The callback must return an instance of \Spatie\ElasticsearchQueryBuilder\Builder.',
        );

        $response = $this->createElasticResponse('_search.json');
        $this->mockClientGet(self::ES_HOST . '/test_items/_search', $response);

        $this->Index->find(function ($builder): void {
            $this->assertInstanceOf(Builder::class, $builder);
        });
    }

    public function testsetSettings(): void
    {
        $instance = $this->Index->setSettings([
            'number_of_shards' => 1,
            'number_of_replicas' => 0,
        ]);

        $this->assertInstanceOf(TestItemsIndex::class, $instance);
    }

    public function testGetSettings(): void
    {
        $settings = $this->Index->getConfig('settings');
        $this->assertIsArray($settings);
        $this->assertArrayHasKey('number_of_shards', $settings);
        $this->assertArrayHasKey('number_of_replicas', $settings);
    }

    public function testsetMappings(): void
    {
        $instance = $this->Index->setMappings([]);

        $this->assertInstanceOf(TestItemsIndex::class, $instance);
    }

    public function testGetMappings(): void
    {
        $mappings = $this->Index->getConfig('mappings');
        $this->assertIsArray($mappings);
        $this->assertArrayHasKey('properties', $mappings);
    }

    public function testCreateIndex(): void
    {
        $response = $this->createElasticResponse()
            ->withStatus(200);
        $this->mockClientPut(self::ES_HOST . '/test_items', $response);

        $result = $this->Index->createIndex();
        $this->assertTrue($result);
    }

    public function testUpdateIndex(): void
    {
        $response = $this->createElasticResponse()
            ->withStatus(200);
        $this->mockClientPut(self::ES_HOST . '/test_items/_mapping', $response);

        $result = $this->Index->updateIndex();
        $this->assertTrue($result);
    }

    public function testDeleteIndex(): void
    {
        $response = $this->createElasticResponse()
            ->withStatus(200);
        $this->mockClientDelete(self::ES_HOST . '/test_items', $response);

        $result = $this->Index->deleteIndex();
        $this->assertTrue($result);
    }

    public function testIndexExists(): void
    {
        $response = $this->createElasticResponse()
            ->withStatus(200);

        Client::addMockResponse('HEAD', self::ES_HOST . '/test_items', $response);

        $result = $this->Index->indexExists();
        $this->assertTrue($result);
    }

    public function testMixinMethod(): void
    {
        $this->assertInstanceOf(Indices::class, $this->Index->indices());
    }

    public function testGet(): void
    {
        $response = $this->createElasticResponse('_document.json');
        $this->mockClientGet(self::ES_HOST . '/test_items/_doc/1', $response);

        $response = $this->Index->get('1');
        $this->assertInstanceOf(Document::class, $response);
        $this->assertEquals('1', $response->getDocumentId());
        $this->assertEquals('Test Item', $response->name);
        $this->assertFalse($response->isDirty());
    }

    public function testGetInvalid(): void
    {
        $response = $this->createElasticResponse('_document_not_found.json')
            ->withStatus(404);

        $this->mockClientGet(self::ES_HOST . '/test_items/_doc/999', $response);

        $this->expectException(ClientResponseException::class);
        $this->Index->get('999');
    }
}
