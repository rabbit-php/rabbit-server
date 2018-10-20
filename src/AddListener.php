<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-23
 * Time: 下午5:45
 */

namespace rabbit\server;


use rabbit\App;
use rabbit\core\ObjectFactory;
use rabbit\helper\ArrayHelper;

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
        foreach ($this->listen as $schme => $data) {
            list($server, $type, $method) = $data;
            $config = ObjectFactory::get($schme);
            /**
             * @var Server $server
             */
            $server = ObjectFactory::get($server);
            if ($type) {
                $port = App::getServer()->listen($server->getHost(), $server->getPort(), $type);
            } else {
                $port = App::getServer()->listen($server->getHost(), $server->getPort());
            }
            foreach ($method as $bind => $callBack) {
                $port->on($bind, [$server, $callBack]);
            }
            $port->set($config);
        }
    }
}