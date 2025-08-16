<?php
declare(strict_types=1);

namespace ElasticKit;

use ArrayIterator;
use Cake\Collection\CollectionTrait;
use Cake\Core\App;
use Cake\Datasource\ResultSetInterface;
use Cake\Utility\Inflector;
use Elastic\Elasticsearch\Response\Elasticsearch;
use IteratorIterator;

/**
 * @package ElasticKit
 */
class ResultSet extends IteratorIterator implements ResultSetInterface
{
    use CollectionTrait;

    protected ?string $documentClass = null;

    /**
     * ResultSet constructor.
     *
     * @param \Elastic\Elasticsearch\Response\Elasticsearch $response The Elasticsearch response.
     * @param string $indexName The name of the index.
     */
    public function __construct(
        protected Elasticsearch $response,
        protected string $indexName,
    ) {
        $items = new ArrayIterator($this->getResults());

        parent::__construct($items);
    }

    /**
     * Get the time taken for the search.
     */
    public function getTook(): ?int
    {
        return $this->response->took ?? null;
    }

    /**
     * Get the total number of hits.
     */
    public function getMaxScore(): ?float
    {
        return $this->response->hits->max_score ?? null;
    }

    /**
     * Get the shards from the response.
     */
    public function getShards(): ?array
    {
        return $this->response->_shards;
    }

    /**
     * Get the total number of hits.
     */
    public function getHitsTotal(): ?int
    {
        return $this->response->hits->total->value ?? null;
    }

    /**
     * Get the aggregations from the response.
     */
    public function getAggregations(): array
    {
        $aggregations = (array)$this->response->aggregations;
        array_walk_recursive($aggregations, function (&$item): void {
            if (is_object($item)) {
                $item = (array)$item;
            }
        });

        return $aggregations;
    }

    /**
     * Get the return documents from the response.
     */
    public function getResults(): array
    {
        $results = [];

        if ($this->response->items) {
            $results = $this->response->items;
        } elseif ($this->response->_id) {
            $results[] = $this->response->asObject();
        } elseif ($this->response->hits) {
            $results = $this->response->hits->hits;
        }

        return $results;
    }

    /**
     * Get the current document.
     */
    public function current(): Document
    {
        $row = parent::current();
        $documentClass = $this->getDocumentClass();
        $errors = [];

        $data = [
            'id' => null,
            'score' => null,
        ];

        // For normal search responses.
        if ($row?->_source) {
            $data['id'] = $row->_id;
            $data['score'] = $row->_score ?? null;
            $data += (array)$row->_source;
        // For batch responses.
        } elseif ($row?->index) {
            $data['id'] = $row->index->_id;

            if ($row->index->error) {
                $errors[] = $row->index->error;
            }
        }

        /** @var \ElasticKit\Document $document */
        $document = new $documentClass($data, [
            'markClean' => true,
            'useSetters' => false,
            'source' => $this->indexName,
        ]);

        if ($errors !== []) {
            $document->setErrors($errors);
        }

        return $document;
    }

    /**
     * Create a document entity from the given data.
     */
    protected function getDocumentClass(): string
    {
        if (!$this->documentClass) {
            $name = Inflector::singularize($this->indexName);
            $name = Inflector::classify($name);

            $className = App::className($name, 'Model/Document');

            if (!$className || !class_exists($className)) {
                $className = Document::class;
            }

            $this->documentClass = $className;
        }

        return $this->documentClass;
    }

    /**
     * Set the document class name.
     */
    public function setDocumentClass(string $className): void
    {
        $this->documentClass = $className;
    }

    /**
     * Get the Elasticsearch response.
     */
    public function hasErrors(): bool
    {
        return $this->response->errors ?? false;
    }

    /**
     * Get the original Elasticsearch response.
     *
     * @return \Elastic\Elasticsearch\Response\Elasticsearch
     */
    public function getResponse(): Elasticsearch
    {
        return $this->response;
    }
}
