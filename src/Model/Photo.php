<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Model;

/**
 * Photo reference returned by POST /animals/{id}/photos.
 */
final class Photo
{
	/** @var int */
	private $id;

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
		$photo = new self();
		$photo->id = (int)($data['id'] ?? 0);
		$photo->raw = $data;

		return $photo;
	}

	public function getId(): int
	{
		return $this->id;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return $this->raw;
	}
}
