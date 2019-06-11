<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/15
 * Time: 17:38
 */

namespace rabbit\server;

/**
 * Class RuntimeHandler
 * @package rabbit\server
 */
class RuntimeHandler implements WorkerHandlerInterface
{
    /**
     *
     */
    public function handle(int $worker_id): void
    {
        \Swoole\Runtime::enableCoroutine();
    }

}