<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Model;

/**
 * Result of POST /animals/{id}/procedures: the visit plus the created records.
 */
final class ProcedureBatch
{
	/** @var int */
	private $appointmentId;

	/** @var list<Procedure> */
	private $procedures;

	/**
	 * @param list<Procedure> $procedures
	 */
	private function __construct(int $appointmentId, array $procedures)
	{
		$this->appointmentId = $appointmentId;
		$this->procedures = $procedures;
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

		return new self((int)($data['appointment_id'] ?? 0), $procedures);
	}

	public function getAppointmentId(): int
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
}
