<?php

declare(strict_types=1);

namespace Rabbit\Server;

/**
 * Class ServerHelper
 * @package Rabbit\Server
 */
class ServerHelper
{
    /** @var Server */
    private static ?Server $_server = null;
    /** @var CoServer */
    private static ?CoServer $_coServer = null;

    /**
     * @return CoServer|Server
     */
    public static function getServer()
    {
        if (self::$_server !== null) {
            return self::$_server;
        }
        return self::$_coServer;
    }

    /**
     * @param Server $server
     */
    public static function setServer(Server $server): void
    {
        self::$_server = $server;
    }

    /**
     * @param CoServer $server
     */
    public static function setCoServer(CoServer $server): void
    {
        self::$_coServer = $server;
    }
    /**
     * @author Albert <63851587@qq.com>
     * @param array $msg
     * @param integer $workerId
     * @param float $wait
     * @return boolean
     */
    public static function sendMessage(array &$msg, int $workerId, float $wait = 0): bool
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
