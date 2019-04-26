<?php

namespace Kreait\Firebase\Messaging;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Kreait\Firebase\Exception\MessagingException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use function GuzzleHttp\Psr7\stream_for;

class BatchApiClient
{
    const PART_BOUNDARY = '__END_OF_PART__';

    /**
     * @var ClientInterface
     */
    private $client;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    public function sendMessages(array $messages): ResponseInterface
    {
        $requests = array_map(function ($request) {
            return [
                'url' => $this->buildMessagingUri('messages:send'),
                'body' => $message,
            ];
        }, $messages);

        $requestHeaders = [
            'Content-Type' => 'multipart/mixed; boundary='.self::PART_BOUNDARY,
        ];

        return $this->request('POST', null, [
            'timeout' => 10,
            'body' => $this->getMultipartPayload($requests),
            'headers' => $requestHeaders,
        ]);
    }

    private function request($method, $endpoint, array $options = null): ResponseInterface
    {
        $options = $options ?? [];

        /** @var UriInterface $uri */
        $uri = $this->client->getConfig('base_uri');
        $path = rtrim($uri->getPath(), '/'). empty($endpoint)? '' : '/'.ltrim($endpoint, '/');
        $uri = $uri->withPath($path);

        try {
            return $this->client->request($method, $uri, $options);
        } catch (RequestException $e) {
            throw MessagingException::fromRequestException($e);
        } catch (\Throwable $e) {
            throw new MessagingException($e->getMessage(), $e->getCode(), $e);
        }
    }

    private function buildMessagingUri($endpoint)
    {
        $uri = $this->client->getConfig('messaging_uri');
        $path = rtrim($uri->getPath(), '/'). empty($endpoint)? '' : '/'.ltrim($endpoint, '/');
        return $uri->withPath($path);
    }

    private function getMultipartPayload(array $requests)
    {
        $stream = '';

        foreach ($requests as $i => $request) {
            $stream .= $this->createPart($request, self::PART_BOUNDARY, $i);
        }

        $stream .= "--".self::PART_BOUNDARY."--\r\n";

        return stream_for($stream);
    }

    private function createPart($request, $boundary, $i)
    {
        $serializedRequest = $this->serializeSubRequest($request);

        $part = "--".self::PART_BOUNDARY."\r\n";
        $part .= "Content-Length: ".strlen($serializedRequest)."\r\n";
        $part .= "Content-Type: application/http\r\n";
        $part .= "content-id: ".($i + 1)."\r\n";
        $part .= "content-transfer-encoding: binary\r\n";
        $part .= "\r\n";
        $part .= $serializedRequest . "\r\n";

        return part;
    }

    private function serializeSubRequest($request)
    {
        $requestBody = json_encode($request['body']);

        $messagePayload = "POST ".$request['url']." HTTP/1.1\r\n";
        $messagePayload .= "Content-Length: ".strlen($requestBody)."\r\n";
        $messagePayload .= "Content-Type: application/json; charset=UTF-8\r\n";

        if (!empty($request['headers'])) {
            foreach ($request['headers'] as $key => $val) {
                $messagePayload .= $key.": ".$val."\r\n";
            }
        }

        $messagePayload .= "\r\n";
        $messagePayload .= $requestBody;

        return $messagePayload;
    }
}
