<?php

declare(strict_types=1);

namespace Rabbit\Server;

/**
 * Class ServerHelper
 * @package Rabbit\Server
 */
class ServerHelper
{
    private static ?Server $_server = null;

    private static ?CoServer $_coServer = null;

    public static function getServer(): null|Server|CoServer
    {
        if (self::$_server !== null) {
            return self::$_server;
        }
        return self::$_coServer;
    }

    public static function setServer(Server $server): void
    {
        self::$_server = $server;
    }

    public static function setCoServer(CoServer $server): void
    {
        self::$_coServer = $server;
    }

    public static function sendMessage(IPCMessage $msg, int $workerId, float $wait = 0): bool
    {
        $server = self::getServer();
        if ($server instanceof Server) {
            $server->pipeHandler->sendMessage($msg, $workerId, $wait);
        } elseif ($server instanceof CoServer) {
            $server->getProcessSocket()->send($msg, $workerId, $wait);
        } else {
            return false;
        }
        return true;
    }
}
