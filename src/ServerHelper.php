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

    private static int $processNum = 0;

    private static int $lockId = -1;

    public static function setNum(int $num): void
    {
        self::$processNum = $num;
    }

    public static function getNum(): int
    {
        return self::$processNum;
    }

    public static function setLockId(int $id): void
    {
        self::$lockId = $id;
    }

    public static function getLockId(): int
    {
        return self::$lockId;
    }

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

    public static function sendMessage(IPCMessage $msg)
    {
        $server = self::getServer();
        if ($server instanceof Server) {
            $msg = $server->pipeHandler->sendMessage($msg);
        } elseif ($server instanceof CoServer) {
            $msg = $server->getProcessSocket()->send($msg);
        } else {
            $msg = create(ProcessSocket::class)->send($msg);
        }
        if ($msg->error !== null) {
            throw $msg->error;
        }
        return $msg->data;
    }
}
