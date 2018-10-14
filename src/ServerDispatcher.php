<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/9
 * Time: 18:35
 */

namespace rabbit\server;


use Psr\Http\Server\MiddlewareInterface;
use rabbit\contract\DispatcherInterface;

abstract class ServerDispatcher implements DispatcherInterface
{
    /**
     * @var MiddlewareInterface[]
     */
    protected $middlewares = [];
}