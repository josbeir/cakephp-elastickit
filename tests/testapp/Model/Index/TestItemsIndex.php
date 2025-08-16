<?php
declare(strict_types=1);

namespace ElasticKit\TestApp\Model\Index;

use ElasticKit\Index;

class TestItemsIndex extends Index
{
    /**
     * @inheritDoc
     */
    public function initialize(): void
    {
        $this->setSettings([
            'number_of_shards' => 1,
            'number_of_replicas' => 1,
        ]);

        $this->setMappings([
            'properties' => [
                'title' => ['type' => 'text'],
                'created' => ['type' => 'date'],
                'modified' => ['type' => 'date'],
            ],
        ]);
    }
}
