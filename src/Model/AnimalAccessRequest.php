<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Model;

/**
 * State of a partner's access to an animal, as returned by
 * POST/GET /v1/partner/animals/{id}/access-request.
 *
 *  - granted : you already have (or were just granted) edit access — no waiting.
 *  - pending : a request is awaiting the owner's decision.
 *  - denied  : the owner declined; you may request again once it expires.
 *  - none    : no active request (GET only).
 */
final class AnimalAccessRequest
{
	const STATUS_GRANTED = 'granted';
	const STATUS_PENDING = 'pending';
	const STATUS_DENIED = 'denied';
	const STATUS_NONE = 'none';

	/** @var string */
	private $status;

	/** @var string|null ISO 8601 — when the request was raised (null when granted/none). */
	private $requestedAt;

	/** @var string|null ISO 8601 — when the request expires and you may retry (null when granted/none). */
	private $expiresAt;

	/** @var int|null Seconds until you may request again (0 once elapsed; null when granted/none). */
	private $retryAfterSeconds;

	/** @var array<string, mixed> Raw payload for forward compatibility. */
	private $raw;

	private function __construct()
	{
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		$state = new self();
		$state->status = (string)($data['status'] ?? self::STATUS_NONE);
		$state->requestedAt = isset($data['requested_at']) ? (string)$data['requested_at'] : null;
		$state->expiresAt = isset($data['expires_at']) ? (string)$data['expires_at'] : null;
		$state->retryAfterSeconds = isset($data['retry_after_seconds'])
			? (int)$data['retry_after_seconds']
			: null;
		$state->raw = $data;

		return $state;
	}

	public function getStatus(): string
	{
		return $this->status;
	}

	public function getRequestedAt(): ?string
	{
		return $this->requestedAt;
	}

	public function getExpiresAt(): ?string
	{
		return $this->expiresAt;
	}

	public function getRetryAfterSeconds(): ?int
	{
		return $this->retryAfterSeconds;
	}

	public function isGranted(): bool
	{
		return $this->status === self::STATUS_GRANTED;
	}

	public function isPending(): bool
	{
		return $this->status === self::STATUS_PENDING;
	}

	public function isDenied(): bool
	{
		return $this->status === self::STATUS_DENIED;
	}

	public function isNone(): bool
	{
		return $this->status === self::STATUS_NONE;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return $this->raw;
	}
}
