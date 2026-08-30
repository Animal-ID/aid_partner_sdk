<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Model;

/**
 * Clinic card as returned by the provisioning plane — both the search results and the clinic you
 * just provisioned.
 */
final class Clinic
{
	/** @var string Stable public clinic identifier; pass it wherever a clinic is named. */
	private $publicId;

	/** @var string|null Clinic name. */
	private $name;

	/** @var string|null Full address; present in search results. */
	private $fullAddress;

	/**
	 * @var int|null Moderation status. A clinic you provisioned stays out of the public directory
	 *               until its director claims it.
	 */
	private $status;

	/**
	 * @var bool|null Whether this clinic is one you provisioned yourself. Null outside search
	 *                results, where the question does not arise.
	 */
	private $linked;

	/**
	 * @var bool|null True when this call created the clinic, false when it resolved the one you
	 *                already had. Null in search results.
	 */
	private $created;

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
		$clinic = new self();
		$clinic->publicId = (string)($data['public_id'] ?? '');
		// Search returns `org_name`, provisioning returns `name` — same thing.
		$clinic->name = isset($data['name'])
			? (string)$data['name']
			: (isset($data['org_name']) ? (string)$data['org_name'] : null);
		$clinic->fullAddress = isset($data['full_address']) ? (string)$data['full_address'] : null;
		$clinic->status = isset($data['status']) ? (int)$data['status'] : null;
		$clinic->linked = isset($data['linked']) ? (bool)$data['linked'] : null;
		$clinic->created = isset($data['created']) ? (bool)$data['created'] : null;
		$clinic->raw = $data;

		return $clinic;
	}

	public function getPublicId(): string
	{
		return $this->publicId;
	}

	public function getName(): ?string
	{
		return $this->name;
	}

	public function getFullAddress(): ?string
	{
		return $this->fullAddress;
	}

	public function getStatus(): ?int
	{
		return $this->status;
	}

	/**
	 * Whether you provisioned this clinic yourself.
	 *
	 * Worth checking before seating a doctor: a clinic that is not yours needs its director's
	 * approval first (see {@see \AnimalId\PartnerSdk\Resource\ConsentsResource::requestClinicMembership()}).
	 */
	public function isLinked(): ?bool
	{
		return $this->linked;
	}

	public function wasCreated(): ?bool
	{
		return $this->created;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return $this->raw;
	}
}
