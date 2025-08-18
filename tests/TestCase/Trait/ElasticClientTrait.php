<?php
declare(strict_types=1);

namespace ElasticKit\Test\Trait;

use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\Http\Client;
use Cake\Http\Client\Response;
use Cake\Http\TestSuite\HttpClientTrait;
use Elastic\Elasticsearch\Response\Elasticsearch;
use ElasticKit\Datasource\Connection;

trait ElasticClientTrait
{
    use HttpClientTrait;

    protected const ES_HOST = 'http://localhost:9200';

    /**
     * Initialize the Elasticsearch client and connection.
     */
    public function initializeElasticClient(): void
    {
        ConnectionManager::drop('elasticsearch');
        ConnectionManager::drop('test_elasticsearch');
        ConnectionManager::setConfig('test_elasticsearch', [
            'className' => Connection::class,
        ]);
    }

    /**
     * Create a mock Elasticsearch response.
     *
     * @param null|array $body
     */
    public function createElasticResponse(?string $resultFile = null): Response
    {
        $body = '';
        if (!empty($resultFile)) {
            $pluginPath = Plugin::path('ElasticKit');
            $path = $pluginPath . 'tests' . DIRECTORY_SEPARATOR . 'results' . DIRECTORY_SEPARATOR . $resultFile;
            if (!file_exists($resultFile)) {
                $body = file_get_contents($path);
            }
        }

        return new Response([
            Elasticsearch::HEADER_CHECK . ':' . Elasticsearch::PRODUCT_NAME,
            'Content-Type: application/json',
        ], $body);
    }

    /**
     * Add a mock response for a GET request.
     *
     * @param string $url The URL to mock
     * @param \Cake\Http\Client\Response $response The response for the mock.
     * @param array<string, mixed> $options Additional options. See Client::addMockResponse()
     */
    public function mockClientHead(string $url, Response $response, array $options = []): void
    {
        Client::addMockResponse('HEAD', $url, $response, $options);
    }
}
