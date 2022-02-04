<?php
declare(strict_types=1);

namespace Rabbit\Server;

abstract class AbstractTcpServer extends Server
{
    protected function createServer(): \Swoole\Server
    {
        return new \Swoole\Server($this->host, $this->port, $this->type);
    }

    protected function startServer(\Swoole\Server $server): void
    {
        parent::startServer($server);
        $server->on('Receive', array($this, 'onReceive'));
        $server->start();
    }
}
