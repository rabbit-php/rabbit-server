<?php

declare(strict_types=1);

use Rabbit\Server\ProcessLock;
use Rabbit\Server\ProcessShare;

if (!function_exists('process_share')) {
    function process_share(string $key, callable $func, int $timeout = 3, string $type = 'share'): ProcessShare
    {
        return ProcessShare::getShare($key, $timeout, $type)($func);
    }
}

if (!function_exists('lock')) {
    function lock(string $key, callable $func, bool $next = true, int $timeout = 3): void
    {
        ProcessLock::getLock($key, $timeout)($key, $func, $next, $timeout);
    }
}
