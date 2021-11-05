<?php

declare(strict_types=1);

namespace Fintecture\Payment\Logger;

class Logger extends \Monolog\Logger
{
    public function __construct($name = 'fintecture', array $handlers = [], array $processors = [])
    {
        parent::__construct($name, $handlers, $processors);
    }
}
