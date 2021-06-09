<?php

declare(strict_types=1);

namespace Rabbit\Server;

use Rabbit\Base\Exception\InvalidArgumentException;
use Throwable;

class CommonHandler
{
    public static function handler($ctl, IPCMessage $msg): IPCMessage
    {
        try {
            if ($msg->isCallable) {
                $msg->data = call_user_func($msg->data);
            } elseif (is_array($msg->data)) {
                [$route, &$params] = $msg->data;
                if (is_string($route)) {
                    if (strpos($route, '::') !== false) {
                        $msg->data = call_user_func_array($route, $params);
                    } elseif (strpos($route, '->')) {
                        [$class, $method] = explode('->', $route);
                        $msg->data = getDI($class)->$method(...$params);
                    } elseif (class_exists($route)) {
                        $msg->data = getDI($route)(...$params);
                    } elseif (method_exists($ctl, $route)) {
                        $msg->data = $ctl->$route(...$params);
                    } else {
                        throw new InvalidArgumentException("The $route params error");
                    }
                } elseif (is_array($route)) {
                    $msg->data = call_user_func_array($route, $params);
                } else {
                    throw new InvalidArgumentException("The route params error");
                }
            } else {
                throw new InvalidArgumentException("The route params error");
            }
        } catch (Throwable $exception) {
            $msg->data = null;
            $msg->error = $exception;
        } finally {
            return $msg;
        }
    }
}
