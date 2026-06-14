<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Exception;

/**
 * HTTP 409 — an X-Eternity-Idempotency-Key was reused with a different body,
 * or the original request is still being processed.
 */
final class ConflictException extends ApiException
{
}
