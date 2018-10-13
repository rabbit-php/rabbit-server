<?php
/**
 * Created by PhpStorm.
 * User: albert
 * Date: 18-5-17
 * Time: 下午9:42
 */

namespace rabbit\server;


interface BootInterface
{
    public function handle(Server $server = null);
}