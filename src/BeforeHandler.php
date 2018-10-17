<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/15
 * Time: 17:38
 */

namespace rabbit\server;

use Swoole\Atomic;
use rabbit\App;

/**
 * Class BeforeHandler
 * @package rabbit\server
 */
class BeforeHandler implements BootInterface
{
    public function handle(): void
    {
        \Swoole\Runtime::enableCoroutine();
        $swooleServer = App::getServer();

        //设置原子计数
        $swooleServer->atomic = new Atomic(0);
    }

}