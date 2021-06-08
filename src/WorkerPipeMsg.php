<?php

declare(strict_types=1);

namespace Rabbit\Server;

class WorkerPipeMsg extends AbstractPipeMsg
{
    public function pipeMessage(\Swoole\Server $server, &$data): void
    {
        CommonHandler::handler($this, $data);
    }
}
