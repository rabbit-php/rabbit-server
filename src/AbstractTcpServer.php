<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/26
 * Time: 20:53
 */

namespace Rabbit\Server;

/**
 * Class AbstractTcpServer
 * @package rabbit\pool
 */
abstract class AbstractTcpServer extends Server
{
    /**
     * @return \Swoole\Server
     */
    protected function createServer(): \Swoole\Server
    {
        return new \Swoole\Server($this->host, $this->port, $this->type);
    }

    /**
     * @param \Swoole\Server|null $server
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    protected function startServer(\Swoole\Server $server = null): void
    {
        parent::startServer($server);
        $server->on('Receive', array($this, 'onReceive'));
        $server->start();
    }
}
