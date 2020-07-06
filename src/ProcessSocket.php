<?php


namespace Rabbit\Server;

/**
 * Class ProcessSocket
 * @package rabbit\httpserver\websocket
 */
class ProcessSocket extends AbstractProcessSocket
{
    /**
     * @param string $data
     * @return string
     * @throws \Exception
     */
    public function handle(array &$data)
    {
        return CommonHandler::handler($this, $data);
    }
}
