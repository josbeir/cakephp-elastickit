<?php
declare(strict_types=1);

namespace ElasticKit\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;
use ElasticKit\Test\Trait\ElasticClientTrait;

class IndexCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;
    use ElasticClientTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeElasticClient();
        $this->loadPlugins(['ElasticKit']);
    }

    protected function expectIndexExist(bool $exists = true): void
    {
        $response = $this->createElasticResponse()
            ->withStatus($exists ? 200 : 404);

        $this->mockClientHead(self::ES_HOST . '/test_items', $response);
    }

    public function testIndexLookup(): void
    {
        $this->exec('elasticsearch index invalid_index');
        $this->assertExitError();
        $this->assertErrorContains('Index class "TestApp\Model\Index\InvalidIndexIndex" not found.');
    }

    public function testPluginIndexLookup(): void
    {
        $this->exec('elasticsearch index MyPlugin.invalid_index');
        $this->assertExitError();
        // Based on this error message we are sure that index from MyPlugin is resolved correctly.
        $this->assertErrorContains('Index class "MyPlugin\Model\Index\InvalidIndexIndex" not found.');
    }

    public function testDelete(): void
    {
        $this->expectIndexExist();
        $response = $this->createElasticResponse()
            ->withStatus(200);

        $this->mockClientDelete(self::ES_HOST . '/test_items', $response);
        $this->exec('elasticsearch index test_items -d');
        $this->assertExitSuccess();
        $this->assertOutputContains('Deleting index on index ');
    }

    public function testDeleteExists(): void
    {
        $this->expectIndexExist(false);
        $response = $this->createElasticResponse()
            ->withStatus(200);

        $this->mockClientDelete(self::ES_HOST . '/test_items', $response);
        $this->exec('elasticsearch index test_items -d');
        $this->assertExitSuccess();
        $this->assertErrorContains('Index does not exist');
    }

    public function testCreate(): void
    {
        $this->expectIndexExist(false);
        $response = $this->createElasticResponse()
            ->withStatus(200);

        $this->mockClientPut(self::ES_HOST . '/test_items', $response);
        $this->exec('elasticsearch index test_items -c');
        $this->assertExitSuccess();
        $this->assertOutputContains('Creating new index');
    }

    public function testCreateExists(): void
    {
        $this->expectIndexExist(true);
        $response = $this->createElasticResponse()
            ->withStatus(200);

        $this->mockClientPut(self::ES_HOST . '/test_items', $response);
        $this->exec('elasticsearch index test_items -c');
        $this->assertExitSuccess();
        $this->assertErrorContains('Index already exists');
    }

    public function testUpdate(): void
    {
        $this->expectIndexExist();
        $response = $this->createElasticResponse()
            ->withStatus(200);

        $this->mockClientPut(self::ES_HOST . '/test_items/_mapping', $response);
        $this->exec('elasticsearch index test_items -u');
        $this->assertExitSuccess();
        $this->assertOutputContains('Updating index schema on index');
    }

    public function testUpdateExists(): void
    {
        $this->expectIndexExist(false);
        $response = $this->createElasticResponse()
            ->withStatus(200);

        $this->mockClientPut(self::ES_HOST . '/test_items/_mapping', $response);
        $this->exec('elasticsearch index test_items -u');
        $this->assertExitSuccess();
        $this->assertErrorContains('Index does not exist');
    }

    public function testVerbose(): void
    {
        $response = $this->createElasticResponse('_settings.json')
            ->withStatus(200);

        $this->mockClientGet(self::ES_HOST . '/test_items/_settings?ignore_unavailable=true', $response);
        $this->mockClientGet(self::ES_HOST . '/test_items/_mapping?ignore_unavailable=true', $response);

        $this->exec('elasticsearch index test_items -v');
        $this->assertOutputContains('Index Settings');
        $this->assertOutputContains('Index Mappings');
    }
}
