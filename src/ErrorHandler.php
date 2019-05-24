<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/15
 * Time: 11:28
 */

namespace rabbit\server;


use Psr\Http\Message\ResponseInterface;
use rabbit\core\Context;
use rabbit\handler\ErrorHandlerInterface;
use rabbit\helper\ExceptionHelper;
use rabbit\helper\JsonHelper;
use rabbit\web\HttpException;

class ErrorHandler implements ErrorHandlerInterface
{
    /**
     * @param \Throwable $throw
     */
    public function handle(\Throwable $throw): ResponseInterface
    {
        $response = $this->handleThrowtable($throw);

        return $response;
    }

    /**
     * @param \Throwable $throwable
     * @return ResponseInterface
     * @throws \Exception
     */
    private function handleThrowtable(\Throwable $exception): ResponseInterface
    {
        /* @var ResponseInterface $response */
        $response = Context::get('response', false);
        if ($response === null) {
            throw $exception;
        }
        $message = ExceptionHelper::convertExceptionToArray($exception);
        if ($exception instanceof HttpException) {
            $response = $response->withStatus($exception->statusCode);
        } else {
            $response = $response->withStatus(500);
        }
        $response = $response->withContent(JsonHelper::encode($message));

        return $response;
    }

}