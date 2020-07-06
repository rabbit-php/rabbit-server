<?php
declare(strict_types=1);

namespace Rabbit\Server;

use Rabbit\Base\Core\ObjectFactory;
use Throwable;

/**
 * Class AddListener
 * @package Rabbit\Server
 */
class AddListener implements BootInterface
{
    /**
     * @var array
     */
    private array $listen = [];

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        foreach ($this->listen as $name => $data) {
            list($server, $type, $method, $schema) = $data;
            $config = ObjectFactory::get($schema);
            if ($type) {
                $port = ServerHelper::getServer()->getSwooleServer()->listen($server->getHost(), $server->getPort(), $type);
            } else {
                $port = ServerHelper::getServer()->getSwooleServer()->listen($server->getHost(), $server->getPort());
            }
            foreach ($method as $bind => $callBack) {
                $port->on($bind, [$server, $callBack]);
            }
            $port->set($config);
        }
    }
}
