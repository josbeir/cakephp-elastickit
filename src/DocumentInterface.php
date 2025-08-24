<?php
declare(strict_types=1);

namespace ElasticKit;

use Cake\Datasource\EntityInterface;

interface DocumentInterface extends EntityInterface
{
    /**
     * Set the document ID.
     */
    public function setDocumentId(mixed $id): self;

    /**
     * Set the document score.
     */
    public function setScore(?float $score): self;
}
