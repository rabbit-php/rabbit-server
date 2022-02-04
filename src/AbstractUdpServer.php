<?php
declare(strict_types=1);

namespace Rabbit\Server;

abstract class AbstractUdpServer extends Server
{
    protected function createServer(): \Swoole\Server
    {
        return new \Swoole\Server($this->host, $this->port, $this->type, SWOOLE_SOCK_UDP);
    }

    protected function startServer(\Swoole\Server $server): void
    {
        parent::startServer($server);
        $server->on('Packet', array($this, 'onPacket'));
        $server->start();
    }
}
