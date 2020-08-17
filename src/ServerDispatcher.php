<?php
declare(strict_types=1);

namespace Rabbit\Server;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Rabbit\Base\Core\BaseObject;
use Rabbit\Web\DispatcherInterface;
use Rabbit\Web\ErrorHandlerInterface;
use Rabbit\Web\RequestContext;
use Rabbit\Web\ResponseContext;
use Throwable;

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
    public function dispatch(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        RequestContext::set($request);
        ResponseContext::set($response);
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
        $response->send();
        return $response;
    }
}
