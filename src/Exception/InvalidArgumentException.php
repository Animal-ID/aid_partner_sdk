<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Exception;

/**
 * Thrown before any network call when the SDK is used incorrectly
 * (empty credentials, unknown identifier type, unreadable file, ...).
 */
final class InvalidArgumentException extends \InvalidArgumentException implements PartnerSdkException
{
}
