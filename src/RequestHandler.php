<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/14
 * Time: 21:48
 */

namespace rabbit\server;


use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use rabbit\core\Context;
use rabbit\core\ObjectFactory;

/**
 * Class RequestHandler
 * @package rabbit\server
 */
class RequestHandler implements RequestHandlerInterface
{
    /**
     * @var array
     */
    private $middlewares;

    /**
     * @var MiddlewareInterface
     */
    private $default;
    /**
     * @var int
     */
    private $offset = 0;

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws \Exception
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (($this->default instanceof MiddlewareInterface) && empty($this->middlewares[$this->offset])) {
            $handler = $this->default;
        } else {
            $handler = $this->middlewares[$this->offset];
        }
        \is_string($handler) && $handler = ObjectFactory::get($handler);

        if (!$handler instanceof MiddlewareInterface) {
            throw new \InvalidArgumentException('Invalid Handler. It must be an instance of MiddlewareInterface');
        }
        $this->offset++;
        return $handler->process($request, $this);
    }
}