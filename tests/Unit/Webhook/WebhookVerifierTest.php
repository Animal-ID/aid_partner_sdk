<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Tests\Unit\Webhook;

use AnimalId\PartnerSdk\Exception\WebhookVerificationException;
use AnimalId\PartnerSdk\Webhook\WebhookEvent;
use AnimalId\PartnerSdk\Webhook\WebhookVerifier;
use PHPUnit\Framework\TestCase;

final class WebhookVerifierTest extends TestCase
{
	const SECRET = 'whsec_test_secret';
	const PATH = '/animal-id/webhook';
	const NOW = 1750000000;

	private function body(string $event = WebhookEvent::TYPE_ACCESS_APPROVED): string
	{
		return (string)json_encode([
			'id'          => 'evt-1',
			'event'       => $event,
			'occurred_at' => '2026-06-24T09:15:00+00:00',
			'result'      => [
				'animal_id'          => '8xK3pQzVnB7rL2qF',
				'requester_user_gid' => 90231,
				'status'             => $event === WebhookEvent::TYPE_ACCESS_APPROVED ? 'granted' : 'denied',
				'decided_at'         => '2026-06-24T09:15:00+00:00',
			],
		]);
	}

	private function sign(string $body, int $ts, string $path = self::PATH, string $secret = self::SECRET): string
	{
		$canonical = implode("\n", ['POST', $path, hash('sha256', $body), (string)$ts]);

		return hash_hmac('sha256', $canonical, $secret);
	}

	/**
	 * @return array<string, string>
	 */
	private function headers(string $body, int $ts): array
	{
		return [
			'X-Eternity-Webhook-Id'        => 'evt-1',
			'X-Eternity-Webhook-Event'     => WebhookEvent::TYPE_ACCESS_APPROVED,
			'X-Eternity-Webhook-Timestamp' => (string)$ts,
			'X-Eternity-Webhook-Signature' => $this->sign($body, $ts),
		];
	}

	private function verifier(int $tolerance = WebhookVerifier::DEFAULT_TOLERANCE_SECONDS): WebhookVerifier
	{
		return new WebhookVerifier(self::SECRET, $tolerance, static function (): int {
			return self::NOW;
		});
	}

	public function testConstructEventReturnsTypedEventForValidSignature(): void
	{
		$body = $this->body();
		$event = $this->verifier()->constructEvent($body, $this->headers($body, self::NOW), self::PATH);

		self::assertSame('evt-1', $event->getId());
		self::assertSame(WebhookEvent::TYPE_ACCESS_APPROVED, $event->getType());
		self::assertTrue($event->isAccessApproved());
		self::assertFalse($event->isAccessDenied());
		self::assertTrue($event->isAnimalAccessEvent());
		self::assertSame('2026-06-24T09:15:00+00:00', $event->getOccurredAt());
		self::assertSame('8xK3pQzVnB7rL2qF', $event->getAnimalId());
		self::assertSame(90231, $event->getRequesterUserGid());
		self::assertSame('granted', $event->getAccessStatus());
		self::assertSame('2026-06-24T09:15:00+00:00', $event->getDecidedAt());
		self::assertSame('granted', $event->getResult()['status']);
	}

	public function testDeniedEventIsRecognised(): void
	{
		$body = $this->body(WebhookEvent::TYPE_ACCESS_DENIED);
		$headers = [
			'X-Eternity-Webhook-Timestamp' => (string)self::NOW,
			'X-Eternity-Webhook-Signature' => $this->sign($body, self::NOW),
		];

		$event = $this->verifier()->constructEvent($body, $headers, self::PATH);

		self::assertTrue($event->isAccessDenied());
		self::assertSame('denied', $event->getAccessStatus());
	}

	public function testThrowsOnSignatureMismatch(): void
	{
		$body = $this->body();
		$headers = [
			'X-Eternity-Webhook-Timestamp' => (string)self::NOW,
			'X-Eternity-Webhook-Signature' => 'deadbeef',
		];

		$this->expectException(WebhookVerificationException::class);
		$this->verifier()->constructEvent($body, $headers, self::PATH);
	}

	public function testThrowsWhenBodyIsTamperedAfterSigning(): void
	{
		$body = $this->body();
		$headers = $this->headers($body, self::NOW);

		$this->expectException(WebhookVerificationException::class);
		$this->verifier()->constructEvent($body . ' ', $headers, self::PATH);
	}

	public function testThrowsOnStaleTimestamp(): void
	{
		$ts = self::NOW - 1000; // older than the 300s tolerance
		$body = $this->body();
		$headers = [
			'X-Eternity-Webhook-Timestamp' => (string)$ts,
			'X-Eternity-Webhook-Signature' => $this->sign($body, $ts),
		];

		$this->expectException(WebhookVerificationException::class);
		$this->verifier()->constructEvent($body, $headers, self::PATH);
	}

	public function testToleranceZeroSkipsTimestampCheck(): void
	{
		$ts = self::NOW - 100000;
		$body = $this->body();
		$headers = [
			'X-Eternity-Webhook-Timestamp' => (string)$ts,
			'X-Eternity-Webhook-Signature' => $this->sign($body, $ts),
		];

		$event = $this->verifier(0)->constructEvent($body, $headers, self::PATH);
		self::assertSame('evt-1', $event->getId());
	}

	public function testThrowsWhenSignatureHeaderMissing(): void
	{
		$this->expectException(WebhookVerificationException::class);
		$this->verifier()->constructEvent($this->body(), ['X-Eternity-Webhook-Timestamp' => (string)self::NOW], self::PATH);
	}

	public function testThrowsWhenTimestampHeaderMissing(): void
	{
		$body = $this->body();
		$this->expectException(WebhookVerificationException::class);
		$this->verifier()->constructEvent($body, ['X-Eternity-Webhook-Signature' => $this->sign($body, self::NOW)], self::PATH);
	}

	public function testAcceptsServerStyleHeaders(): void
	{
		$body = $this->body();
		$server = [
			'HTTP_X_ETERNITY_WEBHOOK_TIMESTAMP' => (string)self::NOW,
			'HTTP_X_ETERNITY_WEBHOOK_SIGNATURE' => $this->sign($body, self::NOW),
			'REQUEST_URI'                       => self::PATH,
		];

		$event = $this->verifier()->constructEvent($body, $server, self::PATH);
		self::assertSame('evt-1', $event->getId());
	}

	public function testPathIsPartOfTheSignature(): void
	{
		$body = $this->body();
		$headers = $this->headers($body, self::NOW); // signed for self::PATH

		$this->expectException(WebhookVerificationException::class);
		$this->verifier()->constructEvent($body, $headers, '/different/path');
	}

	public function testVerifyReturnsBoolean(): void
	{
		$body = $this->body();

		self::assertTrue($this->verifier()->verify($body, $this->headers($body, self::NOW), self::PATH));
		self::assertFalse($this->verifier()->verify($body, ['X-Eternity-Webhook-Signature' => 'x', 'X-Eternity-Webhook-Timestamp' => (string)self::NOW], self::PATH));
	}

	public function testParseDecodesWithoutVerifying(): void
	{
		$event = $this->verifier()->parse($this->body());
		self::assertSame('animal_access.approved', $event->getType());
	}

	public function testParseRejectsNonEventBody(): void
	{
		$this->expectException(WebhookVerificationException::class);
		$this->verifier()->parse('{"foo":"bar"}');
	}

	public function testEmptySecretIsRejected(): void
	{
		$this->expectException(WebhookVerificationException::class);
		new WebhookVerifier('');
	}
}
