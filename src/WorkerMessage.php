<?php
declare(strict_types=1);

namespace Rabbit\Server;


/**
 * Class WorkerMessage
 * @package rabbit\server
 */
class WorkerMessage
{
    /**
     * @param array $msg
     * @param int $workerId
     */
    public function send(array $msg, int $workerId = -1)
    {
        $server = ServerHelper::getServer()->getSwooleServer();
        if ($workerId === -1) {
            for ($i = 0; $i < $server->setting['worker_num']; $i++) {
                $i !== $server->worker_id && $server->sendMessage($msg, $i);
            }
        } else {
            $workerId >= 0 && $workerId <= $server->setting['worker_num'] && $server->sendMessage($msg, $i);
        }
    }
}
