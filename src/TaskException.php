<?php

declare(strict_types=1);

namespace Rabbit\Server;


use Rabbit\Base\Core\Exception;

class TaskException extends Exception
{
    public function getName(): string
    {
        return 'Task Exception';
    }
}
