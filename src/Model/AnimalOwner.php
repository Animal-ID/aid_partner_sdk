<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Model;

/**
 * An animal's owner as embedded by the `owners` expand (X-Eternity-Expand: ["owners"]).
 * Same shape as {@see Owner} plus the `is_main_owner` flag. Available on the partner surface only.
 */
final class AnimalOwner
{
	/** @var int|null Legacy numeric owner id (pre-2026-07-04 versions). */
	private $userGid;

	/** @var string|null Stable public owner identifier; pass this into animal registration. */
	private $publicId;

	/** @var bool Whether the owner already has a usable account. */
	private $hasAccount;

	/** @var string|null */
	private $email;

	/** @var string|null */
	private $phone;

	/** @var string|null Masked display name (no PII). */
	private $displayHint;

	/** @var string|null Preferred locale. */
	private $language;

	/** @var string|null Zero-padded ISO 3166-1 numeric code (e.g. "804"). */
	private $countryId;

	/** @var bool true for the main owner; false for co-owners. */
	private $isMainOwner;

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
		$owner->publicId = isset($data['public_id']) ? (string)$data['public_id'] : null;
		$owner->hasAccount = (bool)($data['has_account'] ?? false);
		$owner->email = isset($data['email']) ? (string)$data['email'] : null;
		$owner->phone = isset($data['phone']) ? (string)$data['phone'] : null;
		$owner->displayHint = isset($data['display_hint']) ? (string)$data['display_hint'] : null;
		$owner->language = isset($data['language']) ? (string)$data['language'] : null;
		$owner->countryId = isset($data['country_id']) ? (string)$data['country_id'] : null;
		$owner->isMainOwner = (bool)($data['is_main_owner'] ?? false);
		$owner->raw = $data;

		return $owner;
	}

	public function getUserGid(): ?int
	{
		return $this->userGid;
	}

	/**
	 * Stable public owner identifier. Pass this into animal registration (owners[].public_id)
	 * on API version >= 2026-07-04.
	 */
	public function getPublicId(): ?string
	{
		return $this->publicId;
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

	public function getCountryId(): ?string
	{
		return $this->countryId;
	}

	public function isMainOwner(): bool
	{
		return $this->isMainOwner;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return $this->raw;
	}
}
