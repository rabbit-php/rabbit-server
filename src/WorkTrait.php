<?php

namespace rabbit\server;

use rabbit\core\ObjectFactory;

/**
 * Trait WorkTrait
 * @package rabbit\server
 */
trait WorkTrait
{
    /**
     * @param null $server
     * @param $worker_id
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    public function workerStart($server = null, $worker_id):void
    {
        ObjectFactory::reload();
    }
}