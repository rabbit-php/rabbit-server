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
    /**
     * @param array $data
     * @return string
     * @throws Exception
     */
    public function handle(array &$data)
    {
        return CommonHandler::handler($this, $data);
    }
}
