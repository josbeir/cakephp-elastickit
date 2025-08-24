<?php
declare(strict_types=1);

namespace ElasticKit\Exception;

use Cake\Core\Exception\CakeException;

/**
 * Exception class for missing index classes.
 *
 * @package ElasticKit
 */
class MissingIndexClassException extends CakeException
{
    protected string $_messageTemplate = 'Index class %s could not be found.';
}
