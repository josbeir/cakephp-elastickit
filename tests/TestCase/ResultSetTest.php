<?php
declare(strict_types=1);

namespace ElasticKit\Test;

use Cake\Datasource\ResultSetInterface;
use Cake\Http\TestSuite\HttpClientTrait;
use Cake\TestSuite\TestCase;
use ElasticKit\Document;
use ElasticKit\ResultSet;
use ElasticKit\Test\Trait\ElasticClientTrait;
use ElasticKit\TestApp\Model\Document\Custom;
use ElasticKit\TestApp\Model\Index\TestItemsIndex;

class ResultSetTest extends TestCase
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

    public function testSearchResults(): void
    {
        $response = $this->createElasticResponse('_search.json');
        $this->mockClientGet(self::ES_HOST . '/test_items/_search', $response);

        $esResponse = $this->Index->search([
            'index' => 'test_items',
        ]);

        $resultset = new ResultSet($esResponse, 'test_items');

        $this->assertInstanceOf(ResultSetInterface::class, $resultset);

        $this->assertIsArray($resultset->getShards());
        $this->assertEquals(2, $resultset->count());
        $this->assertEquals(1, $resultset->getTook());
        $this->assertEquals(1, $resultset->getMaxScore());
        $this->assertEquals(2, $resultset->getHitsTotal());
        $this->assertEmpty($resultset->getAggregations());

        /** @var \ElasticKit\Document $first */
        $first = $resultset->first();
        $this->assertInstanceOf(Document::class, $first);
        $this->assertEquals('hello', $first->name);
        $this->assertEquals(1, $first->getScore());
        $this->assertEquals(1, $first->getDocumentId());
    }

    public function testSingleResult(): void
    {
        $response = $this->createElasticResponse('_document.json');
        $this->mockClientGet(self::ES_HOST . '/test_items/_doc/1', $response);

        $esResponse = $this->Index->getClient()->get([
            'index' => 'test_items',
            'id' => 1,
        ]);

        $resultset = new ResultSet($esResponse, 'test_items');

        $this->assertInstanceOf(ResultSetInterface::class, $resultset);
        $this->assertEquals(1, $resultset->count());

        /** @var \ElasticKit\Document $first */
        $first = $resultset->first();
        $this->assertInstanceOf(Document::class, $first);
        $this->assertEquals('Test Item', $first->name);
        $this->assertEquals(1, $first->getDocumentId());
    }

    public function testWithAggregations(): void
    {
        $response = $this->createElasticResponse('_search_aggregations.json');
        $esResponse = $this->mockClientGet(self::ES_HOST . '/test_items/_search', $response);

        $esResponse = $this->Index->search([
            'index' => 'test_items',
        ]);

        $resultset = new ResultSet($esResponse, 'test_items');
        $aggregations = $resultset->getAggregations();

        $this->assertIsArray($aggregations);
        $this->assertArrayHasKey('some_id', $aggregations);
    }

    public function testGetResponse(): void
    {
        $response = $this->createElasticResponse('_search.json');
        $this->mockClientGet(self::ES_HOST . '/test_items/_search', $response);

        $esResponse = $this->Index->search([
            'index' => 'test_items',
        ]);

        $resultset = new ResultSet($esResponse, 'test_items');

        $this->assertSame($esResponse, $resultset->getResponse());
    }

    public function testBulk(): void
    {
        $response = $this->createElasticResponse('_bulk.json');
        $this->mockClientPost(self::ES_HOST . '/test_items/_bulk', $response);

        $esResponse = $this->Index->bulk([
            'index' => 'test_items',
            'body' => [],
        ]);

        $resultset = new ResultSet($esResponse, 'test_items');
        $this->assertFalse($resultset->hasErrors());

        /** @var \ElasticKit\Document $first */
        $first = $resultset->first();

        $this->assertInstanceOf(Document::class, $first);
        $this->assertEquals(1, $first->getDocumentId());
    }

    public function testBulkWithErrors(): void
    {
        $response = $this->createElasticResponse('_bulk_errors.json');
        $this->mockClientPost(self::ES_HOST . '/test_items/_bulk', $response);

        $esResponse = $this->Index->bulk([
            'index' => 'test_items',
            'body' => [],
        ]);

        $resultset = new ResultSet($esResponse, 'test_items');
        $this->assertTrue($resultset->hasErrors());

        /** @var \ElasticKit\Document $first */
        $first = $resultset->first();

        $this->assertInstanceOf(Document::class, $first);
        $this->assertEquals(1, $first->getDocumentId());
        $this->assertIsArray($first->getErrors());

        $errors = $first->getErrors();
        $this->assertArrayHasKey('type', $errors[0]);
        $this->assertEquals('document_parsing_exception', $errors[0]['type']);
    }

    public function testSetDocumentClass(): void
    {
        $response = $this->createElasticResponse('_search.json');
        $this->mockClientGet(self::ES_HOST . '/test_items/_search', $response);

        $esResponse = $this->Index->search([
            'index' => 'test_items',
        ]);

        $resultset = new ResultSet($esResponse, 'test_items');
        $resultset->setDocumentClass(Custom::class);

        $first = $resultset->first();
        $this->assertInstanceOf(Custom::class, $first);
    }
}
