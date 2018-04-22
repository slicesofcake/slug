<?php
namespace Slug\Exception;

use Cake\Core\Exception\Exception;

class LengthException extends Exception
{
    /**
     * {@inheritdoc}
     */
    public function __construct($message, $code = 500, $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}