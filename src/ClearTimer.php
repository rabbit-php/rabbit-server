<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/26
 * Time: 14:07
 */

namespace rabbit\server;

use rabbit\core\ObjectFactory;
use rabbit\core\Timer;

/**
 * Class ClearTimer
 * @package rabbit\server
 */
class ClearTimer implements WorkerHandlerInterface
{
    /**
     * @param int $worker_id
     * @throws \Exception
     */
    public function handle(int $worker_id): void
    {
        /** @var Timer|null $timer */
        $timer = ObjectFactory::get('timer', false);
        $timer && $timer->clearTimers();
    }

}