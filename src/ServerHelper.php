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
    private static Server $_server;
    /** @var CoServer */
    private static CoServer $_coServer;

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
}