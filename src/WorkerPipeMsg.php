<?php

declare(strict_types=1);

namespace Rabbit\Server;

class WorkerPipeMsg extends AbstractPipeMsg
{
    public function pipeMessage(\Swoole\Server $server, IPCMessage $msg): IPCMessage
    {
        if ($msg->isCallable) {
            $msg->data = $this->closure->decode($msg->data);
        }
        return create(CommonHandler::class)->handle($this, $msg);
    }
}
