<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Model;

/**
 * Result of POST /animals/{id}/procedures: the visit plus the created records.
 *
 * Without access to the animal the batch is recorded unverified: no visit is opened (the
 * appointment id is null) and getAccessRequest() is the access request the owner will answer.
 */
final class ProcedureBatch
{
	/** @var int|null */
	private $appointmentId;

	/** @var list<Procedure> */
	private $procedures;

	/** @var AnimalAccessRequest|null */
	private $accessRequest;

	/**
	 * @param list<Procedure> $procedures
	 */
	private function __construct(?int $appointmentId, array $procedures, ?AnimalAccessRequest $accessRequest)
	{
		$this->appointmentId = $appointmentId;
		$this->procedures = $procedures;
		$this->accessRequest = $accessRequest;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		$procedures = [];
		if (isset($data['procedures']) && is_array($data['procedures'])) {
			foreach ($data['procedures'] as $procedure) {
				if (is_array($procedure)) {
					$procedures[] = Procedure::fromArray($procedure);
				}
			}
		}

		$accessRequest = isset($data['access_request']) && is_array($data['access_request'])
			? AnimalAccessRequest::fromArray($data['access_request'])
			: null;

		return new self(
			isset($data['appointment_id']) ? (int)$data['appointment_id'] : null,
			$procedures,
			$accessRequest
		);
	}

	/** The visit opened for the batch; null when it was recorded unverified (no access). */
	public function getAppointmentId(): ?int
	{
		return $this->appointmentId;
	}

	/**
	 * @return list<Procedure>
	 */
	public function getProcedures(): array
	{
		return $this->procedures;
	}

	/** The access request an unverified batch was recorded under; null when you have access. */
	public function getAccessRequest(): ?AnimalAccessRequest
	{
		return $this->accessRequest;
	}

	/** Whether the batch awaits the owner's decision (recorded without access). */
	public function isUnverified(): bool
	{
		return $this->accessRequest !== null;
	}
}
