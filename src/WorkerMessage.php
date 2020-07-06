<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/26
 * Time: 22:04
 */

namespace Rabbit\Server;

use rabbit\App;

/**
 * Class WorkerMessage
 * @package rabbit\server
 */
class WorkerMessage
{
    /**
     * @param string $msg
     * @param int $workerId
     */
    public function send(array $msg, int $workerId = -1)
    {
        $server = App::getServer()->getSwooleServer();
        if ($workerId === -1) {
            for ($i = 0; $i < $server->setting['worker_num']; $i++) {
                $i !== $server->worker_id && $server->sendMessage($msg, $i);
            }
        } else {
            $workerId >= 0 && $workerId <= $server->setting['worker_num'] && $server->sendMessage($msg, $i);
        }
    }
}
