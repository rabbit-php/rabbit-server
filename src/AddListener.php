<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-23
 * Time: 下午5:45
 */

namespace Rabbit\Server;

use rabbit\App;
use rabbit\core\ObjectFactory;

class AddListener implements BootInterface
{
    /**
     * @var array
     */
    private $listen = [];

    /**
     * @throws \Exception
     */
    public function handle(): void
    {
        foreach ($this->listen as $name => $data) {
            list($server, $type, $method, $schme) = $data;
            $config = ObjectFactory::get($schme);
            if ($type) {
                $port = App::getServer()->getSwooleServer()->listen($server->getHost(), $server->getPort(), $type);
            } else {
                $port = App::getServer()->getSwooleServer()->listen($server->getHost(), $server->getPort());
            }
            foreach ($method as $bind => $callBack) {
                $port->on($bind, [$server, $callBack]);
            }
            $port->set($config);
        }
    }
}
