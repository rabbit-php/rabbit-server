<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/9
 * Time: 18:35
 */

namespace rabbit\server;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use rabbit\contract\DispatcherInterface;
use rabbit\contract\HandlerInterface;
use rabbit\core\Context;
use rabbit\core\ObjectFactory;
use rabbit\handler\ErrorHandlerInterface;

/**
 * Class ServerDispatcher
 * @package rabbit\server
 */
class ServerDispatcher implements DispatcherInterface
{
    /**
     * @var RequestHandlerInterface
     */
    protected $requestHandler;

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     * @throws \Exception
     */
    public function dispatch(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->beforeDispatch($request, $response);
        try {
            // before dispatcher
            $requestHandler = clone $this->requestHandler;
            $response = $requestHandler->handle($request);
        } catch (\Throwable $throw) {
            /**
             * @var ErrorHandlerInterface $errorHandler
             */
            $errorHandler = ObjectFactory::get('errorHandler');
            $response = $errorHandler->handle($throw);
        }
        $this->afterDispatch($response);
        return $response;
    }

    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     */
    protected function beforeDispatch(RequestInterface $request, ResponseInterface $response): void
    {
        Context::set('request', $request);
        Context::set('response', $response);
    }

    /**
     * @param ResponseInterface $response
     */
    protected function afterDispatch(ResponseInterface $response): void
    {
        $response->send();
    }
}