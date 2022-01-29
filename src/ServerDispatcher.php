<?php

declare(strict_types=1);

namespace Rabbit\Server;

use Throwable;
use Rabbit\Web\ResponseContext;
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
    protected RequestHandlerInterface $requestHandler;

    public function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        try {
            // before dispatcher
            $requestHandler = clone $this->requestHandler;
            $response = $requestHandler->handle($request);
        } catch (Throwable $throw) {
            /**
             * @var ErrorHandlerInterface $errorHandler
             */
            $errorHandler = service('errorHandler');
            $response = ResponseContext::get();
            $errorHandler->handle($throw, $response);
        } finally {
            return $response;
        }
    }
}
