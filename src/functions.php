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

if (!function_exists('process_lock')) {
    function process_lock(string $key, callable $func, int $timeout = 3): mixed
    {
        return ProcessLock::getLock($key, $timeout)($func);
    }
}
