<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Tests\Support;

use AnimalId\PartnerSdk\Exception\TransportException;
use AnimalId\PartnerSdk\Http\HttpClientInterface;
use AnimalId\PartnerSdk\Http\Request;
use AnimalId\PartnerSdk\Http\Response;

/**
 * In-memory transport: returns queued responses and records every request.
 */
final class FakeHttpClient implements HttpClientInterface
{
    /** @var list<Response> */
    private $queue = [];

    /** @var list<Request> */
    private $requests = [];

    public function queue(Response $response): self
    {
        $this->queue[] = $response;

        return $this;
    }

    public function queueJson(int $statusCode, array $data, array $headers = []): self
    {
        return $this->queue(new Response(
            $statusCode,
            ['content-type' => 'application/json'] + $headers,
            json_encode($data, JSON_UNESCAPED_UNICODE)
        ));
    }

    public function send(Request $request): Response
    {
        $this->requests[] = $request;

        if ($this->queue === []) {
            throw new TransportException('FakeHttpClient: no queued response left.');
        }

        return array_shift($this->queue);
    }

    public function lastRequest(): Request
    {
        if ($this->requests === []) {
            throw new \LogicException('FakeHttpClient: no request was sent.');
        }

        return $this->requests[count($this->requests) - 1];
    }

    /**
     * @return list<Request>
     */
    public function getRequests(): array
    {
        return $this->requests;
    }
}
