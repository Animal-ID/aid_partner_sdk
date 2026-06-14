<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Exception;

/**
 * HTTP 413 — the whole request exceeds the gateway limit (15 MB for photo uploads).
 */
final class PayloadTooLargeException extends ApiException
{
}
