<?php
declare(strict_types=1);

namespace ElasticKit\Test\Trait;

use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
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
        ConnectionManager::setConfig('test_elasticsearch', [
            'className' => Connection::class,
        ]);
    }

    /**
     * Cleanup the Elasticsearch client and connection.
     */
    public function cleanupElasticClient(): void
    {
        ConnectionManager::drop('test_elasticsearch');
        $this->cleanupMockResponses();
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
}
