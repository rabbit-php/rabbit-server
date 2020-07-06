<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/9
 * Time: 22:04
 */

namespace Rabbit\Server;

abstract class AbstractUdpServer extends Server
{
    /**
     * @return \Swoole\Server
     */
    protected function createServer(): \Swoole\Server
    {
        return new \Swoole\Server($this->host, $this->port, $this->type, SWOOLE_SOCK_UDP);
    }

    /**
     * @param \Swoole\Server|null $server
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    protected function startServer(\Swoole\Server $server = null): void
    {
        parent::startServer($server);
        $server->on('Packet', array($this, 'onPacket'));
        $server->start();
    }
}
