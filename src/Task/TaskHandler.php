<?php


namespace Rabbit\Server\Task;

use Exception;
use rabbit\server\CommonHandler;

/**
 * Class TaskHandler
 * @package rabbit\server\Task
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
