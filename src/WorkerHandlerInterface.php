<?php

declare(strict_types=1);

namespace Rabbit\Server;

/**
 * Interface WorkerHandlerInterface
 * @package Rabbit\Server
 */
interface WorkerHandlerInterface
{
    public function handle(int $worker_id): void;
}
