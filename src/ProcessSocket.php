<?php


namespace rabbit\server;

use rabbit\App;
use rabbit\exception\InvalidArgumentException;

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
    public function handle(string &$data): string
    {
        try {
            [$route, $params] = $this->parser->decode($data);
            if (is_string($route)) {
                if (strpos($route, '::') !== false) {
                    $result = call_user_func_array($route, $params);
                } elseif (strpos($route, '->')) {
                    [$class, $method] = explode('->', $route);
                    $result = getDI($class)->$method(...$params);
                } elseif (class_exists($route)) {
                    $result = getDI($route)(...$params);
                } elseif (method_exists($this, $route)) {
                    $result = $this->$route(...$params);
                } else {
                    throw new InvalidArgumentException("The $route parame error");
                }
            } elseif (is_array($route)) {
                $result = call_user_func_array($route, $params);
            } else {
                throw new InvalidArgumentException("The route parame error");
            }
        } catch (\Throwable $exception) {
            $result = $exception->getMessage();
            App::error($result);
        } finally {
            return $this->parser->encode($result);
        }
    }
}
