<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Model;

/**
 * Animal card as returned by the lookup/show endpoints.
 */
final class Animal
{
	/** @var string Public animal id (NanoID). */
	private $id;

	/** @var int|null Species dictionary id. */
	private $species;

	/** @var string|null */
	private $breed;

	/** @var string|null */
	private $color;

	/** @var int|null Sex dictionary id. */
	private $genderId;

	/** @var string|null */
	private $nickname;

	/** @var string|null */
	private $microchip;

	/** @var string|null */
	private $qrTag;

	/** @var string|null ISO 8601 date of birth. */
	private $dob;

	/** @var string|null ISO 8601 registration date. */
	private $registerDate;

	/** @var bool|null */
	private $sterilizationStatus;

	/** @var string|null "active" when reported lost, otherwise null. */
	private $lostStatus;

	/** @var bool */
	private $deceased;

	/** @var string|null */
	private $diedAt;

	/** @var int|null */
	private $status;

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
		$animal = new self();
		$animal->id = (string)($data['id'] ?? '');
		$animal->species = isset($data['species']) ? (int)$data['species'] : null;
		$animal->breed = isset($data['breed']) ? (string)$data['breed'] : null;
		$animal->color = isset($data['color']) ? (string)$data['color'] : null;
		$animal->genderId = isset($data['gender_id']) ? (int)$data['gender_id'] : null;
		$animal->nickname = isset($data['nickname']) ? (string)$data['nickname'] : null;
		$animal->microchip = isset($data['microchip']) ? (string)$data['microchip'] : null;
		$animal->qrTag = isset($data['qr_tag']) ? (string)$data['qr_tag'] : null;
		$animal->dob = isset($data['dob']) ? (string)$data['dob'] : null;
		$animal->registerDate = isset($data['register_date']) ? (string)$data['register_date'] : null;
		$animal->sterilizationStatus = isset($data['sterilization_status'])
			? (bool)$data['sterilization_status']
			: null;
		$animal->lostStatus = isset($data['lost_status']) ? (string)$data['lost_status'] : null;
		$animal->deceased = (bool)($data['deceased'] ?? false);
		$animal->diedAt = isset($data['died_at']) ? (string)$data['died_at'] : null;
		$animal->status = isset($data['status']) ? (int)$data['status'] : null;
		$animal->raw = $data;

		return $animal;
	}

	public function getId(): string
	{
		return $this->id;
	}

	public function getSpecies(): ?int
	{
		return $this->species;
	}

	public function getBreed(): ?string
	{
		return $this->breed;
	}

	public function getColor(): ?string
	{
		return $this->color;
	}

	public function getGenderId(): ?int
	{
		return $this->genderId;
	}

	public function getNickname(): ?string
	{
		return $this->nickname;
	}

	public function getMicrochip(): ?string
	{
		return $this->microchip;
	}

	public function getQrTag(): ?string
	{
		return $this->qrTag;
	}

	public function getDob(): ?string
	{
		return $this->dob;
	}

	public function getRegisterDate(): ?string
	{
		return $this->registerDate;
	}

	public function getSterilizationStatus(): ?bool
	{
		return $this->sterilizationStatus;
	}

	public function getLostStatus(): ?string
	{
		return $this->lostStatus;
	}

	public function isLost(): bool
	{
		return $this->lostStatus === 'active';
	}

	public function isDeceased(): bool
	{
		return $this->deceased;
	}

	public function getDiedAt(): ?string
	{
		return $this->diedAt;
	}

	public function getStatus(): ?int
	{
		return $this->status;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return $this->raw;
	}
}
