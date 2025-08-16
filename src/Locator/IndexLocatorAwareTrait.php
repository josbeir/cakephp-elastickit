<?php
declare(strict_types=1);

namespace ElasticKit\Locator;

use ElasticKit\Index;

trait IndexLocatorAwareTrait
{
    protected ?IndexLocatorInterface $indexLocator = null;

    /**
     * Get the index locator instance.
     */
    public function getIndexLocator(): IndexLocatorInterface
    {
        if (!$this->indexLocator) {
            $this->indexLocator = new IndexLocator();
        }

        return $this->indexLocator;
    }

    /**
     * Get an index instance by its alias.
     *
     * @param string $alias The alias of the index.
     * @throws \ElasticKit\Exception\MissingIndexClassException If the index class does not exist.
     */
    public function fetchIndex(string $alias, array $options = []): Index
    {
        return $this->getIndexLocator()->get($alias, $options);
    }
}
