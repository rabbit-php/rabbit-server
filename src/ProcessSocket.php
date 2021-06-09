<?php
declare(strict_types=1);


namespace Rabbit\Server;

use Exception;

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
        return CommonHandler::handler($this, $msg);
    }
}
