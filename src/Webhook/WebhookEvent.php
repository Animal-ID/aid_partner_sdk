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

	/** A permission you asked for was granted; act on it until it expires. */
	const TYPE_CONSENT_APPROVED = 'consent.approved';

	/** The person you asked refused. Nothing was granted. */
	const TYPE_CONSENT_DENIED = 'consent.denied';

	/**
	 * A permission you had was taken back. For a handed-over key it stops working immediately.
	 *
	 * The one outcome you cannot see coming: it can land months after the approval, and without
	 * this event you would learn of it when your next call starts failing.
	 */
	const TYPE_CONSENT_REVOKED = 'consent.revoked';

	/** Nobody answered in time. Not a refusal — you may ask again. */
	const TYPE_CONSENT_EXPIRED = 'consent.expired';

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

	// --- Typed accessors for the consent.* events --------------------------------------------

	public function isConsentApproved(): bool
	{
		return $this->type === self::TYPE_CONSENT_APPROVED;
	}

	public function isConsentDenied(): bool
	{
		return $this->type === self::TYPE_CONSENT_DENIED;
	}

	/**
	 * A permission you held was taken back.
	 *
	 * Handle this one. It can arrive months after the approval, and a handed-over key stops working
	 * the moment it does — without the event you would find out when your next call fails.
	 */
	public function isConsentRevoked(): bool
	{
		return $this->type === self::TYPE_CONSENT_REVOKED;
	}

	/** Nobody answered in time. Not a refusal — you may ask again. */
	public function isConsentExpired(): bool
	{
		return $this->type === self::TYPE_CONSENT_EXPIRED;
	}

	public function isConsentEvent(): bool
	{
		return $this->isConsentApproved()
			|| $this->isConsentDenied()
			|| $this->isConsentRevoked()
			|| $this->isConsentExpired();
	}

	/** Public id of the consent request this outcome belongs to, or null. */
	public function getConsentId(): ?string
	{
		return isset($this->result['consent_id']) ? (string)$this->result['consent_id'] : null;
	}

	/** "key_handover" or "clinic_membership" for consent events, otherwise null. */
	public function getConsentKind(): ?string
	{
		return $this->isConsentEvent() && isset($this->result['kind'])
			? (string)$this->result['kind']
			: null;
	}

	/** Public id of the doctor a key handover concerns; null for a clinic membership. */
	public function getDoctorId(): ?string
	{
		return isset($this->result['doctor_id']) && $this->result['doctor_id'] !== null
			? (string)$this->result['doctor_id']
			: null;
	}

	/** Public id of the clinic a membership concerns; null for a key handover. */
	public function getClinicId(): ?string
	{
		return isset($this->result['clinic_id']) && $this->result['clinic_id'] !== null
			? (string)$this->result['clinic_id']
			: null;
	}
}
