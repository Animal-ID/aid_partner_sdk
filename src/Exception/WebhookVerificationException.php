<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Exception;

/**
 * Thrown by {@see \AnimalId\PartnerSdk\Webhook\WebhookVerifier} when an incoming webhook cannot be
 * trusted: missing signature/timestamp headers, a stale timestamp (replay window exceeded),
 * a signature mismatch, or an unparseable body.
 */
final class WebhookVerificationException extends \RuntimeException implements PartnerSdkException
{
}
