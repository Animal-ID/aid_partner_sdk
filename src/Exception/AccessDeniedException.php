<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Exception;

/**
 * HTTP 403 — authenticated, but not allowed (e.g. no relation to the animal).
 */
final class AccessDeniedException extends ApiException
{
}
