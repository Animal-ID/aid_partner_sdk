<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Exception;

/**
 * Network-level failure: the request never produced an HTTP response
 * (DNS, connection refused, timeout, TLS, ...).
 */
final class TransportException extends \RuntimeException implements PartnerSdkException
{
}
