<?php
declare(strict_types=1);

namespace Rabbit\Server;

/**
 * Interface BootInterface
 * @package Rabbit\Server
 */
interface BootInterface
{
    public function handle(): void;
}
