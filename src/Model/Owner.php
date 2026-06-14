<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Model;

/**
 * Owner card as returned by POST /owners and GET /owners/search.
 */
final class Owner
{
	/** @var int|null */
	private $userGid;

	/** @var bool */
	private $hasAccount;

	/** @var string|null */
	private $email;

	/** @var string|null */
	private $phone;

	/** @var string|null Masked display name (no PII). */
	private $displayHint;

	/** @var string|null */
	private $language;

	/** @var int|null */
	private $countryId;

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
		$owner = new self();
		$owner->userGid = isset($data['user_gid']) ? (int)$data['user_gid'] : null;
		$owner->hasAccount = (bool)($data['has_account'] ?? false);
		$owner->email = isset($data['email']) ? (string)$data['email'] : null;
		$owner->phone = isset($data['phone']) ? (string)$data['phone'] : null;
		$owner->displayHint = isset($data['display_hint']) ? (string)$data['display_hint'] : null;
		$owner->language = isset($data['language']) ? (string)$data['language'] : null;
		$owner->countryId = isset($data['country_id']) ? (int)$data['country_id'] : null;
		$owner->raw = $data;

		return $owner;
	}

	public function getUserGid(): ?int
	{
		return $this->userGid;
	}

	public function hasAccount(): bool
	{
		return $this->hasAccount;
	}

	public function getEmail(): ?string
	{
		return $this->email;
	}

	public function getPhone(): ?string
	{
		return $this->phone;
	}

	public function getDisplayHint(): ?string
	{
		return $this->displayHint;
	}

	public function getLanguage(): ?string
	{
		return $this->language;
	}

	public function getCountryId(): ?int
	{
		return $this->countryId;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return $this->raw;
	}
}
