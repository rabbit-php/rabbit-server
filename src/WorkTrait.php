<?php

namespace rabbit\server;

use rabbit\core\ObjectFactory;

trait WorkTrait
{
    public function workerStart($server = null, $worker_id)
    {
        ObjectFactory::reload();
    }
}