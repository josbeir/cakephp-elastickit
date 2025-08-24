<?php
declare(strict_types=1);

namespace ElasticKit;

use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use ElasticKit\Command\IndexCommand;

/**
 * Plugin for ElasticKit
 *
 * @package ElasticKit
 */
class ElasticKitPlugin extends BasePlugin
{
    /**
     * Add commands for the plugin.
     *
     * @param \Cake\Console\CommandCollection $commands The command collection to update.
     */
    public function console(CommandCollection $commands): CommandCollection
    {
        $commands = parent::console($commands);
        $commands->add('elasticsearch index', IndexCommand::class);

        return $commands;
    }
}
