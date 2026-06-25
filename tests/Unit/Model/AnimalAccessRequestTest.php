<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Tests\Unit\Model;

use AnimalId\PartnerSdk\Model\AnimalAccessRequest;
use PHPUnit\Framework\TestCase;

final class AnimalAccessRequestTest extends TestCase
{
	public function testMapsPendingState(): void
	{
		$state = AnimalAccessRequest::fromArray([
			'status'              => 'pending',
			'requested_at'        => '2026-06-23T09:00:00+00:00',
			'expires_at'          => '2026-06-30T09:00:00+00:00',
			'retry_after_seconds' => 604800,
		]);

		self::assertSame('pending', $state->getStatus());
		self::assertTrue($state->isPending());
		self::assertFalse($state->isGranted());
		self::assertSame('2026-06-23T09:00:00+00:00', $state->getRequestedAt());
		self::assertSame('2026-06-30T09:00:00+00:00', $state->getExpiresAt());
		self::assertSame(604800, $state->getRetryAfterSeconds());
	}

	public function testMapsGrantedState(): void
	{
		$state = AnimalAccessRequest::fromArray(['status' => 'granted']);

		self::assertTrue($state->isGranted());
		self::assertFalse($state->isPending());
		self::assertNull($state->getRequestedAt());
		self::assertNull($state->getRetryAfterSeconds());
	}

	public function testDeniedAndNoneHelpers(): void
	{
		self::assertTrue(AnimalAccessRequest::fromArray(['status' => 'denied'])->isDenied());
		self::assertTrue(AnimalAccessRequest::fromArray(['status' => 'none'])->isNone());
	}

	public function testDefaultsToNoneWhenStatusMissing(): void
	{
		$state = AnimalAccessRequest::fromArray([]);

		self::assertTrue($state->isNone());
		self::assertSame([], $state->toArray());
	}
}
