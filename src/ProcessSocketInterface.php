<?php


namespace rabbit\server;

/**
 * Interface ProcessSocketInterface
 * @package rabbit\server
 */
interface ProcessSocketInterface
{
    /**
     * @param $data
     * @param int|null $workerId
     * @return mixed
     */
    public function send($data, int $workerId = null);

    /**
     * @param array $responses
     * @param string $data
     * @return mixed
     */
    public function handle(string $data): string;
}