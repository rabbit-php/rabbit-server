<?php

declare(strict_types=1);

namespace Rabbit\Server;

/**
 * Class AbstractTask
 * @package Rabbit\Server
 */
abstract class AbstractTask
{
    protected string $logKey = 'Task';

    public function __construct(string $logKey = 'Task')
    {
        $this->logKey = $logKey;
    }

    abstract public function handle(int $task_id, int $from_id, IPCMessage $data);

    public function finish(\Swoole\Server $serv, int $task_id, string $data)
    {
    }
}
