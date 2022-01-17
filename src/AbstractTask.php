<?php

declare(strict_types=1);

namespace Rabbit\Server;

/**
 * Class AbstractTask
 * @package Rabbit\Server
 */
abstract class AbstractTask
{
    public function __construct(protected string $logKey = 'Task')
    {
    }

    abstract public function handle(int $task_id, int $from_id, IPCMessage $data);

    public function finish(\Swoole\Server $serv, int $task_id, string $data)
    {
    }
}
