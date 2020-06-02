<?php
declare(strict_types=1);

namespace rabbit\server;

/**
 * Class WorkerMsg
 * @package rabbit\server
 */
class WorkerHandle extends AbstractPipeMsg
{
    /**
     * @param \Swoole\Server $server
     * @param $data
     * @throws \Exception
     */
    public function pipeMessage(\Swoole\Server $server, &$data): void
    {
        CommonHandler::handler($this, $data);
    }

}