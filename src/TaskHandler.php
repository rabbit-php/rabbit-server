<?php
declare(strict_types=1);

namespace Rabbit\Server;

use Exception;

/**
 * Class TaskHandler
 * @package Rabbit\Server
 */
class TaskHandler extends AbstractTask
{
    public function handle(int $task_id, int $from_id, IPCMessage $data)
    {
        return create(CommonHandler::class)->handler($this, $data);
    }
}
