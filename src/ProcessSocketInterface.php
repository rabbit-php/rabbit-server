<?php


namespace rabbit\server;

/**
 * Interface ProcessSocketInterface
 * @package rabbit\server
 */
interface ProcessSocketInterface
{
    /**
     * @param array $responses
     * @param string $data
     * @return mixed
     */
    public function handle($server, string $data);
}