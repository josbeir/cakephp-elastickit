<?php
declare(strict_types=1);

namespace ElasticKit\Locator;

use Cake\Core\App;
use Cake\Core\Exception\CakeException;
use Cake\Datasource\ConnectionManager;
use Cake\Utility\Inflector;
use ElasticKit\Exception\MissingIndexClassException;
use ElasticKit\Index;
use function Cake\Core\pluginSplit;

/**
 * Class IndexLocator
 *
 * This class is responsible for managing the lifecycle of index instances.
 * It allows for retrieving, setting, checking existence, and removing index instances.
 */
class IndexLocator implements IndexLocatorInterface
{
    protected array $instances = [];

    protected array $options = [];

    /**
     * @inheritDoc
     */
    public function set(string $alias, Index $repository): Index
    {
        return $this->instances[$alias] = $repository;
    }

    /**
     * @inheritDoc
     */
    public function exists(string $alias): bool
    {
        return isset($this->instances[$alias]);
    }

    /**
     * @inheritDoc
     */
    public function remove(string $alias): void
    {
        unset(
            $this->instances[$alias],
            $this->options[$alias],
        );
    }

    /**
     * @inheritDoc
     */
    public function clear(): void
    {
        $this->instances = [];
        $this->options = [];
    }

    /**
     * @inheritDoc
     */
    public function get(string $alias, array $options = []): Index
    {
        $storeOptions = $options;

        if (isset($this->instances[$alias])) {
            if ($storeOptions && isset($this->options[$alias]) && $this->options[$alias] !== $storeOptions) {
                throw new CakeException(sprintf(
                    'You cannot configure `%s`, it already exists in the registry.',
                    $alias,
                ));
            }

            return $this->instances[$alias];
        }

        $this->options[$alias] = $storeOptions;

        return $this->instances[$alias] = $this->createInstance($alias, $options);
    }

    /**
     * Wrapper for creating table instances
     *
     * @param array<string, mixed> $options The alias to check for.
     */
    protected function create(array $options): Index
    {
        /** @var class-string<\ElasticKit\Index> $class */
        $class = $options['className'];

        return new $class($options);
    }

    /**
     * Create a new index instance.
     *
     * @param string $alias The alias for the index.
     * @param array $options Options for the index.
     * @throws \ElasticKit\Exception\MissingIndexClassException If the index class cannot be found.
     * @return \ElasticKit\Index The created index instance.
     */
    protected function createInstance(string $alias, array $options): Index
    {
        [, $classAlias] = pluginSplit($alias);
        $options += [
            'name' => Inflector::underscore($classAlias),
            'className' => Inflector::camelize($alias),
        ];
        $className = App::className($options['className'], 'Model/Index', 'Index');
        if ($className) {
            $options['className'] = $className;
        } else {
            throw new MissingIndexClassException(['name' => $alias]);
        }

        if (empty($options['connection'])) {
            $connectionName = $options['className']::defaultConnectionName();
            $options['connection'] = ConnectionManager::get($connectionName);
        }

        $options['registryAlias'] = $alias;

        return $this->create($options);
    }
}
