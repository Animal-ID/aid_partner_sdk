<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Http;

use AnimalId\PartnerSdk\Exception\TransportException;

/**
 * Transport abstraction so the SDK can be tested without the network
 * and the default cURL client can be swapped for any other implementation.
 */
interface HttpClientInterface
{
    /**
     * @throws TransportException On network-level failures (DNS, timeout, TLS, ...).
     */
    public function send(Request $request): Response;
}
