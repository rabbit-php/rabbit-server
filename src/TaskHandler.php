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
    /**
     * @param int $task_id
     * @param int $from_id
     * @param $data
     * @return bool|mixed
     * @throws Exception
     */
    public function handle(int $task_id, int $from_id, &$data)
    {
        return CommonHandler::handler($this, $data);
    }
}
