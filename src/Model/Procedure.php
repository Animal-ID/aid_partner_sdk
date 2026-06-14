<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Model;

/**
 * Procedure record. Normalizes the two payload shapes returned by the API:
 * POST uses procedure_type_id / performed_at / extra_fields, while
 * GET uses type / occurred_at / type_specific_payload.
 */
final class Procedure
{
	/** @var int */
	private $id;

	/** @var string|null Animal public id (NanoID). */
	private $animalId;

	/** @var int|null Visit/appointment the procedure was recorded under. */
	private $appointmentId;

	/** @var int|null Procedure catalogue id (procedure_types dictionary). */
	private $type;

	/** @var string|int|null ISO 8601 string (GET) or Unix seconds (POST), as returned. */
	private $occurredAt;

	/** @var string|null */
	private $summary;

	/** @var string|null */
	private $revaccinationDate;

	/** @var array<string, mixed> */
	private $typeSpecificPayload;

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
		$procedure = new self();
		$procedure->id = (int)($data['id'] ?? 0);
		$procedure->animalId = isset($data['animal_id']) ? (string)$data['animal_id'] : null;

		$appointmentId = $data['visit_id'] ?? ($data['appointment_id'] ?? null);
		$procedure->appointmentId = $appointmentId !== null ? (int)$appointmentId : null;

		$type = $data['type'] ?? ($data['procedure_type_id'] ?? null);
		$procedure->type = $type !== null ? (int)$type : null;

		$procedure->occurredAt = $data['occurred_at'] ?? ($data['performed_at'] ?? null);
		$procedure->summary = isset($data['summary']) ? (string)$data['summary'] : null;
		$procedure->revaccinationDate = isset($data['revaccination_date'])
			? (string)$data['revaccination_date']
			: null;

		$payload = $data['type_specific_payload'] ?? ($data['extra_fields'] ?? []);
		$procedure->typeSpecificPayload = is_array($payload) ? $payload : [];

		$procedure->raw = $data;

		return $procedure;
	}

	public function getId(): int
	{
		return $this->id;
	}

	public function getAnimalId(): ?string
	{
		return $this->animalId;
	}

	public function getAppointmentId(): ?int
	{
		return $this->appointmentId;
	}

	public function getType(): ?int
	{
		return $this->type;
	}

	/**
	 * @return string|int|null
	 */
	public function getOccurredAt()
	{
		return $this->occurredAt;
	}

	public function getSummary(): ?string
	{
		return $this->summary;
	}

	public function getRevaccinationDate(): ?string
	{
		return $this->revaccinationDate;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getTypeSpecificPayload(): array
	{
		return $this->typeSpecificPayload;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return $this->raw;
	}
}
