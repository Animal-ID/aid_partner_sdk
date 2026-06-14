<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Exception;

/**
 * HTTP 401 — the signature, keys or timestamp were rejected.
 */
final class AuthenticationException extends ApiException
{
}
