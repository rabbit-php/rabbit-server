<?php
declare(strict_types=1);

namespace Rabbit\Server;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Rabbit\Base\Core\Context;
use Rabbit\Web\DispatcherInterface;
use Rabbit\Web\ErrorHandlerInterface;
use Throwable;

/**
 * Class ServerDispatcher
 * @package rabbit\server
 */
class ServerDispatcher implements DispatcherInterface
{
    /**
     * @var RequestHandlerInterface
     */
    protected RequestHandlerInterface $requestHandler;

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     * @throws Throwable
     */
    public function dispatch(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->beforeDispatch($request, $response);
        try {
            // before dispatcher
            $requestHandler = clone $this->requestHandler;
            $response = $requestHandler->handle($request);
        } catch (Throwable $throw) {
            /**
             * @var ErrorHandlerInterface $errorHandler
             */
            $errorHandler = getDI('errorHandler');
            $response = $errorHandler->handle($throw);
        }
        $this->afterDispatch($request, $response);
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
     * @param RequestInterface $request
     * @param ResponseInterface $response
     */
    protected function afterDispatch(RequestInterface $request, ResponseInterface $response): void
    {
        $response->send();
    }
}
