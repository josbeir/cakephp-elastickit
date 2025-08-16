<?php
declare(strict_types=1);

namespace ElasticKit\Panel;

use Cake\Datasource\ConnectionManager;
use DebugKit\DebugPanel;
use ElasticKit\Datasource\Connection;
use ElasticKit\Log\DebugKitLog;
use Psr\Log\LoggerInterface;

class ElasticsearchPanel extends DebugPanel
{
    public string $plugin = 'ElasticKit';

    protected static array $loggers = [];

    /**
     * @inheritDoc
     */
    public function initialize(): void
    {
        $configs = ConnectionManager::configured();

        foreach ($configs as $name) {
            static::addConnection($name);
        }
    }

    /**
     * Add a connection to the list of loggers.
     *
     * @param string $name The name of the connection to add.
     */
    public static function addConnection(string $name): void
    {
        $connection = ConnectionManager::get($name);
        if (!$connection instanceof Connection) {
            return;
        }

        $connectionLogger = $connection->getLogger();
        if (!$connectionLogger instanceof LoggerInterface) {
            return;
        }

        $logger = new DebugKitLog($connectionLogger, $name);
        $connection->setLogger($logger);
        $connection->initialize();

        static::$loggers[$name] = $logger;
    }

    /**
     * Get the name of the panel.
     */
    public function data(): array
    {
        return [
            'loggers' => self::$loggers,
        ];
    }

    /**
     * Get summary data from the queries run.
     */
    public function summary(): string
    {
        $requests = 0;
        foreach (self::$loggers as $logger) {
            $requests += count($logger->requests());
        }

        return (string)$requests;
    }
}
