<?php
declare(strict_types=1);

namespace ElasticKit\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Utility\Inflector;
use ElasticKit\Exception\MissingIndexClassException;
use ElasticKit\Locator\IndexLocatorAwareTrait;
use function Cake\Core\pluginSplit;

/**
 * ElasticIndex command.
 */
class IndexCommand extends Command
{
    use IndexLocatorAwareTrait;

    protected ?string $plugin = null;

    /**
     * Get the command description.
     */
    public static function getDescription(): string
    {
        return 'Manages the ElasticKit index.';
    }

    /**
     * Hook method for defining this command's option parser.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser The parser to be defined
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->addArgument('name', [
                'help' => 'The name of the index to manage.',
                'required' => true,
            ])
            ->addOption('create', [
                'help' => 'Create the index if it does not exist.',
                'short' => 'c',
                'boolean' => true,
            ])
            ->addOption('update', [
                'help' => 'Update the index schema if it exists.',
                'short' => 'u',
                'boolean' => true,
            ])
            ->addOption('delete', [
                'help' => 'Delete the index if it exists.',
                'short' => 'd',
                'boolean' => true,
            ])
            ->setDescription(static::getDescription());
    }

    /**
     * Implement this method with your command's logic.
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $name = $args->getArgument('name') ?? '';
        $name = $this->indexClassName($name);

        try {
            $index = $this->fetchIndex($name);
        } catch (MissingIndexClassException $missingIndexClassException) {
            $io->error(sprintf('Index class "%s" not found.', $name));

            return static::CODE_ERROR;
        }

        if ($args->getOption('delete')) {
            if ($index->indexExists()) {
                $io->success(sprintf('Deleting index on index "%s"', $index->getIndexName()));
                $index->deleteIndex();
            } else {
                $io->warning('Index does not exist. Nothing to delete.');
            }

            return static::CODE_SUCCESS;
        }

        if ($args->getOption('update')) {
            if ($index->indexExists()) {
                $io->success(sprintf('Updating index schema on index "%s".', $index->getIndexName()));
                $index->updateIndex();
            } else {
                $io->warning('Index does not exist. Nothing to update.');
            }

            return static::CODE_SUCCESS;
        }

        if ($args->getOption('create')) {
            if ($index->indexExists()) {
                $io->warning('Index already exists. Nothing to create.');

                return static::CODE_SUCCESS;
            }

            $io->success(sprintf('Creating new index "%s".', $index->getIndexName()));
            $index->createIndex();
        }

        if ($args->getOption('verbose')) {
            $settings = $index->indices()->getSettings([
                'index' => $index->getIndexName(),
                'ignore_unavailable' => true,
            ]);
            $mapping = $index->indices()->getMapping([
                'index' => $index->getIndexName(),
                'ignore_unavailable' => true,
            ]);

            $io->out('Index Settings:');
            $io->out(print_r($settings->asArray(), true));
            $io->hr();
            $io->out('Index Mappings:');
            $io->out(print_r($mapping->asArray(), true));

            return static::CODE_SUCCESS;
        }

        return static::CODE_SUCCESS;
    }

    /**
     * Get the name of the index class
     *
     * @param string $name The name of the index.
     * @return string The index name.
     */
    protected function indexClassName(string $name): string
    {
        $base = Configure::read('App.namespace');
        if (strpos($name, '.')) {
            [$plugin, $name] = pluginSplit($name);
            $base = $plugin;
        }

        return $base . '\\Model\\Index\\' . Inflector::camelize($name) . 'Index';
    }
}
