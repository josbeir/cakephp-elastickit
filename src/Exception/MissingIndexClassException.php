<?php
declare(strict_types=1);

namespace ElasticKit\Exception;

use Cake\Core\Exception\CakeException;

class MissingIndexClassException extends CakeException
{
    protected string $_messageTemplate = 'Index class %s could not be found.';
}
