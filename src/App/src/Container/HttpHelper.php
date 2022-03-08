<?php

namespace App\Container;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

class HttpHelper
{
    private ClientInterface $client;
    private RequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;

    public function __construct(ClientInterface $client, RequestFactoryInterface $requestFactory, StreamFactoryInterface $streamFactory)
    {
        $this->client = $client;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
    }

    public function getHtml(string $url, array $headers = []) : string
    {
        $response = $this->getResponse($url, $headers);

        return (string) $response->getBody();
    }

    public function getJson(string $url, array $headers = []) : ?array
    {
        $headers = array_merge([
            'Accept' => 'application/json;charset=UTF-8',
            'Content-Type' => 'application/json',
        ], $headers);

        $response = $this->getResponse($url, $headers);
        $body = StringUtils::trim((string) $response->getBody());

        if ($body === '') {
            return null;
        }

        return json_decode($body, true, 512, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public function getResponse(string $url, array $headers = []) : ResponseInterface
    {
        $headers = array_merge([
            'User-Agent' => UserAgent::OSX_DESKTOP,
        ], $headers);

        $request = $this->createRequest('GET', $url, $headers);

        return $this->sendRequest($request);
    }

    public function postJson(string $url, $body, array $headers = []) : ?array
    {
        return $this->requestJson('POST', $url, $body, $headers);
    }

    public function patchJson(string $url, $body, array $headers = []) : ?array
    {
        return $this->requestJson('PATCH', $url, $body, $headers);
    }

    public function putJson(string $url, $body, array $headers = []) : ?array
    {
        return $this->requestJson('PUT', $url, $body, $headers);
    }

    public function requestJson(string $method, string $url, $body, array $headers = []) : ?array
    {
        $headers = array_merge([
            'Connection' => 'close',
            'Accept' => 'application/json;charset=UTF-8',
            'User-Agent' => UserAgent::OSX_DESKTOP,
        ], $headers);

        if ($body instanceof StreamInterface) {
            if (!isset($headers['Content-Type'])) {
                $headers['Content-Type'] = 'application/octet-stream';
            }

            $stream = $body;
        } else {
            if (!isset($headers['Content-Type'])) {
                $headers['Content-Type'] = 'application/json;charset=UTF-8';
            }

            $body = is_array($body) ? json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : (string) $body;
            $stream = $this->streamFactory->createStream($body);
        }

        if (($size = $stream->getSize()) !== null) {
            $headers['Content-Length'] = $size;
        }

        $request = $this->createRequest($method, $url, $headers)->withBody($stream);
        $response = $this->sendRequest($request);

        $body = StringUtils::trim((string) $response->getBody());

        if ($body === '') {
            return null;
        }

        return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }

    private function createRequest(string $method, string $url, array $headers = []) : RequestInterface
    {
        $request = $this->requestFactory->createRequest($method, $url);

        foreach ($headers as $header => $value) {
            $request = $request->withHeader($header, $value);
        }

        return $request;
    }

    private function sendRequest(RequestInterface $request) : ResponseInterface
    {
        $response = $this->client->sendRequest($request);

        if ($response->getStatusCode() >= 400) {
            throw new HttpException($request, $response);
        }

        return $response;
    }
}
