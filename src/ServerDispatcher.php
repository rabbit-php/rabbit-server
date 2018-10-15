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

class ServerDispatcher implements DispatcherInterface
{
    /**
     * @var RequestHandlerInterface
     */
    private $requestHandler;

    public function dispatch(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            // before dispatcher
            $this->beforeDispatch($request, $response);
            $response = $this->requestHandler->handle($request);
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

    protected function beforeDispatch(RequestInterface $request, ResponseInterface $response)
    {
        Context::set('request', $request);
        Context::set('response', $response);
        Context::set('mwOffset', 0);
    }

    protected function afterDispatch(ResponseInterface $response)
    {
        $response->send();
        Context::release();
    }
}