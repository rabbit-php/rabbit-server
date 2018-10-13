<?php

namespace rabbit\server;

use rabbit\framework\App;
use rabbit\framework\core\ObjectFactory;

trait WorkTrait
{
    public function workerStart($server = null, $worker_id)
    {
//        \Swoole\Runtime::enableCoroutine();
        ObjectFactory::reload();
    }
}