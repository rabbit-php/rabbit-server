<?php

declare(strict_types=1);

namespace Rabbit\Server;

use Swoole\Server;

class WorkerPipeMsg extends AbstractPipeMsg
{
    /**
     * @author Albert <63851587@qq.com>
     * @param Server $server
     * @param [type] $data
     * @return void
     */
    public function pipeMessage(Server $server, &$data): void
    {
        CommonHandler::handler($this, $data);
    }
}
