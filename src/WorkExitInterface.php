<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/23
 * Time: 17:05
 */

namespace rabbit\server;

/**
 * Class WorkExitInterface
 * @package rabbit\server
 */
interface WorkExitInterface
{
    /**
     * @param int $worker_id
     */
    public function handle(int $worker_id): void;
}