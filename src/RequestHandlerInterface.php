<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/1/18
 * Time: 16:26
 */

namespace rabbit\server;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Interface RequestHandlerInterface
 * @package rabbit\server
 */
interface RequestHandlerInterface
{
    public function __invoke(array $params = [], ServerRequestInterface $request = null);
}