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
    use EntityTrait {
        __debugInfo as entityDebugInfo;
    }

    protected array $reserved = [
        'document_id' => null,
        'score' => null,
    ];

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

    /**
     * Set the document ID.
     */
    public function setDocumentId(mixed $id): self
    {
        $this->reserved['document_id'] = $id;

        return $this;
    }

    /**
     * Set the document score.
     */
    public function setScore(?float $score): self
    {
        $this->reserved['score'] = $score;

        return $this;
    }

    /**
     * Get the document ID.
     */
    public function getDocumentId(): mixed
    {
        return $this->reserved['document_id'];
    }

    /**
     * Get the document score.
     */
    public function getScore(): int|float|null
    {
        return $this->reserved['score'];
    }

    /**
     * Get the source of the document.
     */
    public function __debugInfo(): array
    {
        $fields = $this->entityDebugInfo();
        $fields['[reserved]'] = $this->reserved;

        return $fields;
    }
}
