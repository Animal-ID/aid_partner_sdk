<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Webhook;

use AnimalId\PartnerSdk\Exception\WebhookVerificationException;

/**
 * A decoded webhook delivery from Animal ID.
 *
 * Wire shape: { "id": "<uuid>", "event": "<key>", "occurred_at": "<ISO 8601>", "result": { ... } }.
 * Build one through {@see WebhookVerifier::constructEvent()} (verified) or
 * {@see WebhookVerifier::parse()} (no signature check).
 */
final class WebhookEvent
{
	const TYPE_ACCESS_APPROVED = 'animal_access.approved';
	const TYPE_ACCESS_DENIED = 'animal_access.denied';

	/** @var string Unique delivery id (matches the X-Eternity-Webhook-Id header). */
	private $id;

	/** @var string Event key, e.g. "animal_access.approved". */
	private $type;

	/** @var string|null ISO 8601 time the event occurred. */
	private $occurredAt;

	/** @var array<string, mixed> Event-specific data. */
	private $result;

	/** @var array<string, mixed> The full decoded envelope. */
	private $payload;

	private function __construct()
	{
	}

	/**
	 * Decodes the raw JSON body into an event. Throws when the body is not a JSON object with an
	 * "event" key — that is never a legitimate Animal ID delivery.
	 *
	 * @throws \AnimalId\PartnerSdk\Exception\WebhookVerificationException
	 */
	public static function fromJson(string $rawBody): self
	{
		$decoded = json_decode($rawBody, true);
		if (!is_array($decoded) || !isset($decoded['event'])) {
			throw new WebhookVerificationException(
				'Webhook body is not a valid Animal ID event payload (missing "event").'
			);
		}

		$event = new self();
		$event->id = (string)($decoded['id'] ?? '');
		$event->type = (string)$decoded['event'];
		$event->occurredAt = isset($decoded['occurred_at']) ? (string)$decoded['occurred_at'] : null;
		$event->result = isset($decoded['result']) && is_array($decoded['result']) ? $decoded['result'] : [];
		$event->payload = $decoded;

		return $event;
	}

	public function getId(): string
	{
		return $this->id;
	}

	public function getType(): string
	{
		return $this->type;
	}

	public function getOccurredAt(): ?string
	{
		return $this->occurredAt;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getResult(): array
	{
		return $this->result;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getPayload(): array
	{
		return $this->payload;
	}

	public function isAccessApproved(): bool
	{
		return $this->type === self::TYPE_ACCESS_APPROVED;
	}

	public function isAccessDenied(): bool
	{
		return $this->type === self::TYPE_ACCESS_DENIED;
	}

	public function isAnimalAccessEvent(): bool
	{
		return $this->isAccessApproved() || $this->isAccessDenied();
	}

	// --- Typed accessors for the animal_access.* events --------------------------------------

	/** Public animal id (NanoID) the decision applies to, or null for non-access events. */
	public function getAnimalId(): ?string
	{
		return isset($this->result['animal_id']) ? (string)$this->result['animal_id'] : null;
	}

	/** Global user id of the vet who requested access, or null. */
	public function getRequesterUserGid(): ?int
	{
		return isset($this->result['requester_user_gid']) && $this->result['requester_user_gid'] !== null
			? (int)$this->result['requester_user_gid']
			: null;
	}

	/** "granted" or "denied" for access events, otherwise null. */
	public function getAccessStatus(): ?string
	{
		return isset($this->result['status']) ? (string)$this->result['status'] : null;
	}

	/** ISO 8601 time the owner decided, or null. */
	public function getDecidedAt(): ?string
	{
		return isset($this->result['decided_at']) ? (string)$this->result['decided_at'] : null;
	}
}
