<?php

declare(strict_types=1);

namespace Rabbit\Server;

/**
 * Class ProcessSocket
 * @package Rabbit\Server
 */
class ProcessSocket extends AbstractProcessSocket
{
    public function handle(IPCMessage $msg)
    {
        if ($msg->isCallable) {
            $msg->data = $this->closure->decode($msg->data);
        }
        return create(CommonHandler::class)->andler($this, $msg);
    }
}
