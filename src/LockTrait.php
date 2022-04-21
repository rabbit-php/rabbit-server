<?php

declare(strict_types=1);

namespace Rabbit\Server;

trait LockTrait
{
    protected static array $size = [];

    protected static array $cids = [];

    public static function lock(string $name): int
    {
        if (!isset(self::$size[$name])) {
            self::$size[$name] = 1;
            return 1;
        }
        self::$size[$name]++;
        return self::$size[$name];
    }

    public static function unLock(string $name): int
    {
        self::$size[$name]--;
        if (self::$cids[$name] ?? false) {
            $cid = self::$cids[$name];
            unset(self::$cids[$name]);
            resume($cid);
        }
        return self::$size[$name];
    }

    public static function shared(string $name, int $timeout = 3): void
    {
        share($name, function () use ($name, $timeout): void {
            self::$cids[$name] = getCid();
            if ($timeout > 0) {
                rgo(function () use ($name, $timeout): void {
                    sleep($timeout);
                    if (self::$cids[$name] ?? false) {
                        $cid = self::$cids[$name];
                        unset(self::$cids[$name]);
                        resume($cid);
                    }
                });
            }
            ryield();
        });
    }
}
