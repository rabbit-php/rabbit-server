<?php
declare(strict_types=1);

namespace Rabbit\Server;

use DI\DependencyException;
use DI\NotFoundException;

/**
 * Class AbstractUdpServer
 * @package Rabbit\Server
 */
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
     * @throws DependencyException
     * @throws NotFoundException
     */
    protected function startServer(\Swoole\Server $server = null): void
    {
        parent::startServer($server);
        $server->on('Packet', array($this, 'onPacket'));
        $server->start();
    }
}
