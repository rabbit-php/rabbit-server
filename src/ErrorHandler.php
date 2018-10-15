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
use rabbit\core\Exception;
use rabbit\core\ObjectFactory;
use rabbit\core\UserException;
use rabbit\handler\ErrorHandlerInterface;
use rabbit\helper\JsonHelper;
use rabbit\web\HttpException;

class ErrorHandler implements ErrorHandlerInterface
{
    public function handle(\Throwable $throw)
    {
        $response = $this->handleThrowtable($throw);

        return $response;
    }

    private function handleThrowtable(\Throwable $throwable)
    {
        $message = self::convertExceptionToArray($throwable);

        /* @var ResponseInterface $response */
        $response = Context::get('response');
        if ($exception instanceof HttpException) {
            $response = $response->withStatus($exception->statusCode);
        } else {
            $response = $response->withStatus(500);
        }
        $response = $response->withContent(JsonHelper::encode($message));

        return $response;
    }

    protected function convertExceptionToArray($exception)
    {
        $array = [
            'name' => $exception instanceof Exception ? $exception->getName() : 'Exception',
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
        ];
        if (ObjectFactory::get('debug')) {
            $array['type'] = get_class($exception);
            if (!$exception instanceof UserException) {
                $array['file'] = $exception->getFile();
                $array['line'] = $exception->getLine();
                $array['stack-trace'] = explode("\n", $exception->getTraceAsString());
            }
        }
        if (($prev = $exception->getPrevious()) !== null) {
            $array['previous'] = $this->convertExceptionToArray($prev);
        }

        return $array;
    }

}