<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         4.1.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace ElasticKit\Locator;

use ElasticKit\Index;

/**
 * Registries for repository objects should implement this interface.
 *
 * @package ElasticKit
 */
interface IndexLocatorInterface
{
    /**
     * Get a repository instance from the registry.
     *
     * @param string $alias The alias name you want to get.
     * @param array<string, mixed> $options The options you want to build the table with.
     * @throws \RuntimeException When trying to get alias for which instance
     *   has already been created with different options.
     */
    public function get(string $alias, array $options = []): Index;

    /**
     * Set a repository instance.
     *
     * @param string $alias The alias to set.
     * @param \ElasticKit\Index $repository The repository to set.
     */
    public function set(string $alias, Index $repository): Index;

    /**
     * Check to see if an instance exists in the registry.
     *
     * @param string $alias The alias to check for.
     */
    public function exists(string $alias): bool;

    /**
     * Removes an repository instance from the registry.
     *
     * @param string $alias The alias to remove.
     */
    public function remove(string $alias): void;

    /**
     * Clears the registry of configuration and instances.
     */
    public function clear(): void;
}
