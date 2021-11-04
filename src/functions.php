<?php

declare(strict_types=1);

use Rabbit\Server\ProcessShare;

if (!function_exists('process_share')) {
    function process_share(string $key, callable $func, int $timeout = 3): ProcessShare
    {
        return ProcessShare::getShare($key, $timeout)($func);
    }
}
