<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/9
 * Time: 18:35
 */

namespace rabbit\server;


use rabbit\framework\contract\DispatcherInterface;

abstract class ServerDispatcher implements DispatcherInterface
{
    /**
     * @var array
     */
    protected $preMiddleware = [];

    /**
     * @var array
     */
    protected $afterMiddleware = [];
}