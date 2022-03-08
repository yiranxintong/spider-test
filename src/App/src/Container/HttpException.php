<?php

namespace App\Container;

use Exception;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class HttpException extends Exception implements RequestExceptionInterface
{
    private RequestInterface $request;
    protected ResponseInterface $response;

    public function __construct(RequestInterface $request, ResponseInterface $response, ?Throwable $previous = null)
    {
        $this->request = $request;
        $this->response = $response;

        $message = sprintf(
            '[url] %s [http method] %s [status code] %s [reason phrase] %s',
            $request->getRequestTarget(),
            $request->getMethod(),
            $response->getStatusCode(),
            $response->getReasonPhrase()
        );
        parent::__construct($message, $response->getStatusCode(), $previous);
    }

    public function getRequest() : RequestInterface
    {
        return $this->request;
    }

    public function getResponse() : ResponseInterface
    {
        return $this->response;
    }
}
