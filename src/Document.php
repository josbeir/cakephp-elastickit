<?php
declare(strict_types=1);

namespace ElasticKit;

use Cake\Datasource\EntityInterface;
use Cake\Datasource\EntityTrait;

/**
 * Base class for ElasticKit documents.
 *
 * @mixin \Elastic\Elasticsearch\Client
 */
class Document implements EntityInterface
{
    use EntityTrait;

    /**
     * Constructor.
     *
     * @param array $properties The data to set in the document.
     * @param array $options Options for the document.
     */
    public function __construct(array $properties = [], array $options = [])
    {
        $options += [
            'useSetters' => true,
            'markClean' => false,
            'markNew' => null,
            'guard' => false,
            'source' => null,
        ];

        if ($options['source'] !== null) {
            $this->setSource($options['source']);
        }

        if ($properties !== []) {
            $this->setOriginalField(array_keys($properties));

            if ($options['markClean'] && !$options['useSetters']) {
                $this->_fields = $properties;

                return;
            }

            $this->patch($properties, [
                'asOriginal' => true,
                'setter' => $options['useSetters'],
                'guard' => $options['guard'],
            ]);
        }
    }
}
