<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Resource;

use AnimalId\PartnerSdk\Exception\InvalidArgumentException;
use AnimalId\PartnerSdk\Model\Procedure;
use AnimalId\PartnerSdk\Model\ProcedureBatch;

/**
 * /v1/partner/animals/{id}/procedures and /v1/partner/procedures —
 * record and read medical procedures (vaccinations, identification, ...).
 */
final class ProceduresResource extends AbstractResource
{
    const ANIMALS_PATH = '/v1/partner/animals';
    const PATH = '/v1/partner/procedures';

    const MAX_BATCH_SIZE = 100;

    /** Procedure catalogue ids (procedure_types dictionary). */
    const TYPE_VACCINATION = 10;
    const TYPE_RABIES_VACCINATION = 20;
    const TYPE_TRANSPONDER_IDENTIFICATION = 30;
    const TYPE_TOKEN_IDENTIFICATION = 40;
    const TYPE_DEWORMING = 50;
    const TYPE_STERILIZATION = 60;
    const TYPE_EUTHANASIA = 70;

    /**
     * Records one procedure or a batch (<= 100) for an animal; opens a visit
     * and grants the vet a relation to the animal.
     *
     * @param array<string, mixed>|list<array<string, mixed>> $procedures A single
     *        procedure object (['type' => ..., 'occurred_at' => ...]) or a list of them.
     * @param string|null $idempotencyKey Your own key for safe retries; auto-generated when null.
     */
    public function create(string $animalId, array $procedures, ?string $idempotencyKey = null): ProcedureBatch
    {
        if ($procedures === []) {
            throw new InvalidArgumentException('At least one procedure is required.');
        }
        if ($this->isList($procedures) && count($procedures) > self::MAX_BATCH_SIZE) {
            throw new InvalidArgumentException(
                sprintf('A procedure batch may contain at most %d items.', self::MAX_BATCH_SIZE)
            );
        }

        $response = $this->api->post(
            self::ANIMALS_PATH . '/' . rawurlencode($animalId) . '/procedures',
            $procedures,
            $idempotencyKey
        );

        return ProcedureBatch::fromArray($this->unwrapSingle($this->payload($response)));
    }

    /**
     * Lists procedures of an animal, optionally filtered.
     *
     * @param int|null    $type  Procedure catalogue id (see TYPE_* constants).
     * @param string|null $since Only on/after this ISO 8601 time.
     * @param string|null $until Only on/before this ISO 8601 time.
     *
     * @return list<Procedure>
     */
    public function listForAnimal(
        string $animalId,
        ?int $type = null,
        ?string $since = null,
        ?string $until = null
    ): array {
        $response = $this->api->get(self::ANIMALS_PATH . '/' . rawurlencode($animalId) . '/procedures', [
            'type' => $type,
            'since' => $since,
            'until' => $until,
        ]);

        return $this->mapList($this->payload($response), [Procedure::class, 'fromArray']);
    }

    /**
     * @throws \AnimalId\PartnerSdk\Exception\NotFoundException When the procedure does not exist.
     */
    public function get(int $id): Procedure
    {
        $response = $this->api->get(self::PATH . '/' . $id);

        return Procedure::fromArray($this->unwrapSingle($this->payload($response)));
    }

    /**
     * @param array<mixed> $value
     */
    private function isList(array $value): bool
    {
        return $value === [] || array_keys($value) === range(0, count($value) - 1);
    }
}
