<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Auth;

/**
 * Generates RFC 4122 version 4 UUIDs for the X-Eternity-Idempotency-Key header.
 */
class IdempotencyKeyGenerator
{
    public function generate(): string
    {
        $bytes = random_bytes(16);

        // Set the version (0100) and variant (10xx) bits.
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
