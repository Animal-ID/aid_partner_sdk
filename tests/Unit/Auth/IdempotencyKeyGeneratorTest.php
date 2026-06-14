<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Tests\Unit\Auth;

use AnimalId\PartnerSdk\Auth\IdempotencyKeyGenerator;
use PHPUnit\Framework\TestCase;

final class IdempotencyKeyGeneratorTest extends TestCase
{
    public function testGeneratesRfc4122Version4Uuid(): void
    {
        $generator = new IdempotencyKeyGenerator();

        for ($i = 0; $i < 50; $i++) {
            self::assertMatchesRegularExpression(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
                $generator->generate()
            );
        }
    }

    public function testGeneratesUniqueKeys(): void
    {
        $generator = new IdempotencyKeyGenerator();

        $keys = [];
        for ($i = 0; $i < 100; $i++) {
            $keys[] = $generator->generate();
        }

        self::assertCount(100, array_unique($keys));
    }
}
