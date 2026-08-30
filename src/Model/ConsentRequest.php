<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Model;

/**
 * A permission you asked someone for, and where it stands.
 *
 * Two things a partner may want that are not theirs to take:
 *
 *   - **key_handover** — hold credentials that act as a doctor. Only that doctor can allow it,
 *     because the key signs as them.
 *   - **clinic_membership** — seat a doctor in a clinic you did not provision. Its director
 *     decides, because it is their organization's member list.
 *
 * An approval is not permanent: it carries the same expiry as the request, and the person can take
 * it back at any time — at which point a handed-over key stops working. Handle the
 * `consent.revoked` webhook rather than discovering it when a call fails.
 */
final class ConsentRequest
{
	const KIND_KEY_HANDOVER = 'key_handover';
	const KIND_CLINIC_MEMBERSHIP = 'clinic_membership';

	const STATUS_PENDING = 'pending';
	const STATUS_APPROVED = 'approved';
	const STATUS_DENIED = 'denied';
	const STATUS_EXPIRED = 'expired';
	const STATUS_REVOKED = 'revoked';

	/** @var string Poll the status endpoint with this. */
	private $publicId;

	/** @var string key_handover | clinic_membership */
	private $kind;

	/** @var string pending | approved | denied | expired | revoked */
	private $status;

	/** @var int|null Unix seconds. An unanswered request closes itself at this moment. */
	private $expiresAt;

	/** @var int|null Unix seconds, or null while nobody has answered. */
	private $decidedAt;

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
		$request = new self();
		$request->publicId = (string)($data['public_id'] ?? '');
		$request->kind = (string)($data['kind'] ?? '');
		$request->status = (string)($data['status'] ?? '');
		$request->expiresAt = isset($data['expires_at']) ? (int)$data['expires_at'] : null;
		$request->decidedAt = isset($data['decided_at']) ? (int)$data['decided_at'] : null;
		$request->raw = $data;

		return $request;
	}

	public function getPublicId(): string
	{
		return $this->publicId;
	}

	public function getKind(): string
	{
		return $this->kind;
	}

	public function getStatus(): string
	{
		return $this->status;
	}

	public function getExpiresAt(): ?int
	{
		return $this->expiresAt;
	}

	public function getDecidedAt(): ?int
	{
		return $this->decidedAt;
	}

	public function isPending(): bool
	{
		return $this->status === self::STATUS_PENDING;
	}

	/**
	 * Whether you may act on this permission **right now**.
	 *
	 * Not the same as "approved": an approval stops working when its window closes, so a status
	 * check alone would let you act on a year-old yes.
	 */
	public function isUsable(): bool
	{
		return $this->status === self::STATUS_APPROVED
			&& ($this->expiresAt === null || $this->expiresAt > time());
	}

	/**
	 * Whether the ask is closed for good — answered no, run out, or taken back.
	 *
	 * `expired` is deliberately not `denied`: nobody refused, nobody looked. You may ask again.
	 */
	public function isFinished(): bool
	{
		return in_array(
			$this->status,
			[self::STATUS_DENIED, self::STATUS_EXPIRED, self::STATUS_REVOKED],
			true
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return $this->raw;
	}
}
