<?php

declare(strict_types=1);

namespace Rabbit\Server;

use Throwable;
use Rabbit\Base\Core\BaseObject;
use Rabbit\Web\DispatcherInterface;
use Rabbit\Web\ErrorHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class ServerDispatcher
 * @package rabbit\server
 */
class ServerDispatcher extends BaseObject implements DispatcherInterface
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
    public function dispatch(ServerRequestInterface $request, ResponseInterface $response): void
    {
        try {
            // before dispatcher
            $requestHandler = clone $this->requestHandler;
            $response = $requestHandler->handle($request);
        } catch (Throwable $throw) {
            /**
             * @var ErrorHandlerInterface $errorHandler
             */
            $errorHandler = getDI('errorHandler');
            $errorHandler->handle($throw, $response);
        }
    }
}
