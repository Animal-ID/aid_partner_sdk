<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Webhook;

use AnimalId\PartnerSdk\Exception\WebhookVerificationException;

/**
 * Verifies and decodes incoming Animal ID webhooks.
 *
 * Deliveries are signed with the per-app webhook secret (shown once in your cabinet) using the same
 * scheme as outbound partner requests, keyed with that secret:
 *
 *     canonical = "POST" + "\n" + path[?query] + "\n" + sha256_hex(rawBody) + "\n" + timestamp
 *     signature = hex( hmac_sha256(canonical, webhookSecret) )
 *
 * The `path` is the path (and query, if any) of the webhook URL you configured, exactly as your
 * server received it — e.g. `$_SERVER['REQUEST_URI']` (no rewriting proxy in between).
 *
 *     $verifier = new WebhookVerifier($webhookSecret);
 *     $event = $verifier->constructEvent(
 *         file_get_contents('php://input'),
 *         $_SERVER,                     // or getallheaders()
 *         $_SERVER['REQUEST_URI']
 *     );
 *     if ($event->isAccessApproved()) { ... }
 */
final class WebhookVerifier
{
	const HEADER_ID = 'X-Eternity-Webhook-Id';
	const HEADER_EVENT = 'X-Eternity-Webhook-Event';
	const HEADER_TIMESTAMP = 'X-Eternity-Webhook-Timestamp';
	const HEADER_SIGNATURE = 'X-Eternity-Webhook-Signature';

	/** Default replay window: reject deliveries whose timestamp is more than 5 minutes off. */
	const DEFAULT_TOLERANCE_SECONDS = 300;

	/** @var string */
	private $secret;

	/** @var int Seconds of allowed clock skew; 0 disables the timestamp check. */
	private $tolerance;

	/** @var callable():int */
	private $clock;

	/**
	 * @param string $secret Your app's webhook signing secret.
	 * @param int $tolerance Replay window in seconds; pass 0 to skip the timestamp check.
	 * @param callable():int|null $clock Returns current Unix time; injectable for tests.
	 */
	public function __construct(
		string $secret,
		int $tolerance = self::DEFAULT_TOLERANCE_SECONDS,
		?callable $clock = null
	) {
		if ($secret === '') {
			throw new WebhookVerificationException('Webhook secret must not be empty.');
		}

		$this->secret = $secret;
		$this->tolerance = $tolerance;
		$this->clock = $clock !== null ? $clock : static function (): int {
			return time();
		};
	}

	/**
	 * Verifies the signature (and timestamp) and returns the decoded event.
	 * Throws {@see WebhookVerificationException} when the delivery cannot be trusted.
	 *
	 * @param string $rawBody The exact raw request body bytes.
	 * @param array<string, mixed> $headers Request headers — accepts getallheaders() style
	 *                                      ("X-Eternity-Webhook-...") or $_SERVER ("HTTP_X_ETERNITY_...").
	 * @param string $path The request path including the query string, as received.
	 *
	 * @throws \AnimalId\PartnerSdk\Exception\WebhookVerificationException
	 */
	public function constructEvent(string $rawBody, array $headers, string $path): WebhookEvent
	{
		$this->assertSignature($rawBody, $headers, $path);

		return WebhookEvent::fromJson($rawBody);
	}

	/**
	 * Boolean form of {@see constructEvent()} — true when the delivery is authentic.
	 *
	 * @param array<string, mixed> $headers
	 */
	public function verify(string $rawBody, array $headers, string $path): bool
	{
		try {
			$this->assertSignature($rawBody, $headers, $path);

			return true;
		} catch (WebhookVerificationException $e) {
			return false;
		}
	}

	/**
	 * Decodes the body WITHOUT verifying the signature. Only use after a successful
	 * {@see verify()}, or when verification happens elsewhere.
	 *
	 * @throws \AnimalId\PartnerSdk\Exception\WebhookVerificationException
	 */
	public function parse(string $rawBody): WebhookEvent
	{
		return WebhookEvent::fromJson($rawBody);
	}

	/**
	 * Recomputes the signature and compares it in constant time; enforces the replay window.
	 *
	 * @param array<string, mixed> $headers
	 *
	 * @throws \AnimalId\PartnerSdk\Exception\WebhookVerificationException
	 */
	private function assertSignature(string $rawBody, array $headers, string $path): void
	{
		$signature = $this->header($headers, self::HEADER_SIGNATURE);
		$timestamp = $this->header($headers, self::HEADER_TIMESTAMP);

		if ($signature === null || $signature === '') {
			throw new WebhookVerificationException('Missing ' . self::HEADER_SIGNATURE . ' header.');
		}
		if ($timestamp === null || $timestamp === '') {
			throw new WebhookVerificationException('Missing ' . self::HEADER_TIMESTAMP . ' header.');
		}

		if ($this->tolerance > 0) {
			$now = (int)call_user_func($this->clock);
			if (abs($now - (int)$timestamp) > $this->tolerance) {
				throw new WebhookVerificationException(sprintf(
					'Webhook timestamp %s is outside the allowed tolerance of %d seconds.',
					$timestamp,
					$this->tolerance
				));
			}
		}

		$canonical = implode("\n", ['POST', $path, hash('sha256', $rawBody), $timestamp]);
		$expected = hash_hmac('sha256', $canonical, $this->secret);

		if (!hash_equals($expected, $signature)) {
			throw new WebhookVerificationException('Webhook signature mismatch.');
		}
	}

	/**
	 * Case-insensitive header lookup that also understands $_SERVER's HTTP_* form, so callers can
	 * pass either getallheaders() or $_SERVER directly.
	 *
	 * @param array<string, mixed> $headers
	 */
	private function header(array $headers, string $name): ?string
	{
		$target = strtolower($name);
		$serverKey = 'http_' . str_replace('-', '_', $target);

		foreach ($headers as $key => $value) {
			$normalized = strtolower((string)$key);
			if ($normalized === $target || $normalized === $serverKey) {
				if (is_array($value)) {
					$value = $value[0] ?? null;
				}

				return $value !== null ? (string)$value : null;
			}
		}

		return null;
	}
}
