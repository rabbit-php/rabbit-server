<?php


namespace rabbit\server;

use rabbit\App;
use rabbit\exception\InvalidArgumentException;
use rabbit\parser\ParserInterface;
use rabbit\parser\PhpParser;
use Swoole\Process;
use Swoole\Process\Pool;

/**
 * Class ProcessSocket
 * @package rabbit\httpserver\websocket
 */
class ProcessSocket implements ProcessSocketInterface
{
    /** @var ParserInterface */
    protected $parser;

    protected $pool;

    /**
     * ProcessSocket constructor.
     */
    public function __construct()
    {
        $this->parser = new PhpParser();
    }

    /**
     * @param $pool
     */
    public function setPool($pool): void
    {
        $this->pool = $pool;
    }

    /**
     * @return Process
     */
    public function getProcess(?int $workerId = null): Process
    {
        $workerId = $workerId ?? $this->workerId;
        return $this->pool instanceof Pool ? $this->pool->getProcess($workerId) : $this->pool[$workerId];
    }

    /**
     * @param $data
     * @param int|null $workerId
     * @return mixed
     */
    public function send($data, int $workerId = null)
    {
        $socket = $this->getProcess($workerId)->exportSocket();
        $socket->send($this->parser->encode($data));
        return $this->parser->decode($socket->recv());
    }


    /**
     * @param string $data
     * @return string
     * @throws \Exception
     */
    public function handle(string $data): string
    {
        try {
            [$route, $params] = $this->parser->decode($data);
            if (is_string($route)) {
                if (strpos($route, '::') !== false) {
                    $result = call_user_func_array($route, $params);
                } elseif (class_exists($route)) {
                    $result = getDI($route)($msg);
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