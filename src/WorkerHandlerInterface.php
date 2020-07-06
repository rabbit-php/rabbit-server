<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/23
 * Time: 17:05
 */

namespace Rabbit\Server;

/**
 * Class WorkerHandlerInterface
 * @package rabbit\server
 */
interface WorkerHandlerInterface
{
    /**
     * @param int $worker_id
     */
    public function handle(int $worker_id): void;
}
