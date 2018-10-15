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
     * Process the request using the current middleware.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws \InvalidArgumentException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $offset = Context::get('mwOffset');
        if (($this->default instanceof MiddlewareInterface) && empty($this->middlewares[$offset])) {
            $handler = $this->default;
        } else {
            $handler = $this->middlewares[$offset];
        }
        \is_string($handler) && $handler = ObjectFactory::get($handler);

        if (!$handler instanceof MiddlewareInterface) {
            throw new \InvalidArgumentException('Invalid Handler. It must be an instance of MiddlewareInterface');
        }
        Context::set('mwOffset', $offset);
        return $handler->process($request, $this);
    }

    /**
     * Insert middlewares to the next position
     *
     * @param array $middlewares
     * @param null $offset
     * @return $this
     */
    public function insertMiddlewares(array $middlewares, $offset = null)
    {
        null === $offset && $offset = $this->offset;
        $chunkArray = array_chunk($this->middlewares, $offset);
        $after = [];
        $before = $chunkArray[0];
        if (isset($chunkArray[1])) {
            $after = $chunkArray[1];
        }
        $middlewares = array_merge((array)$before, $middlewares, (array)$after);
        $this->middlewares = $middlewares;
        return $this;
    }
}