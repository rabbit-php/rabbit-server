<?php

declare(strict_types=1);

namespace Rabbit\Server;

use Rabbit\Base\Exception\InvalidArgumentException;
use Throwable;

/**
 * Class CommonHandler
 * @package Rabbit\Server
 */
class CommonHandler
{
    public static function handler($ctl, array &$data)
    {
        $result = null;
        try {
            [$route, &$params] = $data;
            if (is_string($route)) {
                if (strpos($route, '::') !== false) {
                    $result = call_user_func_array($route, $params);
                } elseif (strpos($route, '->')) {
                    [$class, $method] = explode('->', $route);
                    $result = getDI($class)->$method(...$params);
                } elseif (class_exists($route)) {
                    $result = getDI($route)(...$params);
                } elseif (method_exists($ctl, $route)) {
                    $result = $ctl->$route(...$params);
                } else {
                    throw new InvalidArgumentException("The $route params error");
                }
            } elseif (is_array($route)) {
                $result = call_user_func_array($route, $params);
            } else {
                throw new InvalidArgumentException("The route params error");
            }
        } catch (Throwable $exception) {
            $result = $exception;
        } finally {
            return $result;
        }
    }
}
