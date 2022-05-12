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
        if ($id > 1) {
            self::$lockId = $id;
        }
    }

    public static function getLockId(): int
    {
        return self::$lockId;
    }

    public static function getServer(): null|Server|CoServer
    {
        return self::$_server;
    }

    public static function setServer(Server|CoServer $server): void
    {
        self::$_server = $server;
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
