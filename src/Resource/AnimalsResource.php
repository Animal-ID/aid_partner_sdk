<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Resource;

use AnimalId\PartnerSdk\Exception\InvalidArgumentException;
use AnimalId\PartnerSdk\Model\Animal;

/**
 * /v1/partner/animals — register, look up and update animals.
 */
final class AnimalsResource extends AbstractResource
{
	const PATH = '/v1/partner/animals';

	const IDENTIFIER_MICROCHIP = 'microchip';
	const IDENTIFIER_QR_TAG = 'qr_tag';

	/**
	 * Registers an animal and returns its public id (NanoID).
	 *
	 * Required: species, nickname, is_microchip (+ microchip when is_microchip is true).
	 *
	 * @param array<string, mixed> $animal
	 * @param string|null $idempotencyKey Your own key for safe retries; auto-generated when null.
	 */
	public function create(array $animal, ?string $idempotencyKey = null): string
	{
		$response = $this->api->post(self::PATH, $animal, $idempotencyKey);
		$payload = $this->unwrapSingle($this->payload($response));

		return (string)($payload['id'] ?? '');
	}

	public function get(string $id): Animal
	{
		$response = $this->api->get(self::PATH . '/' . rawurlencode($id));

		return Animal::fromArray($this->unwrapSingle($this->payload($response)));
	}

	/**
	 * Looks up animals by a specific identifier type.
	 *
	 * @param string $type One of the IDENTIFIER_* constants (microchip, qr_tag).
	 *
	 * @return list<Animal> Usually one element; a value may resolve to several.
	 */
	public function findByIdentifier(string $type, string $value): array
	{
		if (!in_array($type, [self::IDENTIFIER_MICROCHIP, self::IDENTIFIER_QR_TAG], true)) {
			throw new InvalidArgumentException(sprintf(
				'Unknown identifier type "%s"; expected "%s" or "%s".',
				$type,
				self::IDENTIFIER_MICROCHIP,
				self::IDENTIFIER_QR_TAG
			));
		}

		$response = $this->api->get(
			self::PATH . '/by-identifier/' . rawurlencode($type) . '/' . rawurlencode($value)
		);

		return $this->mapList($this->payload($response), [Animal::class, 'fromArray']);
	}

	/**
	 * Looks up animals by identifier value across microchip and qr_tag.
	 *
	 * @return list<Animal>
	 */
	public function findByAnyIdentifier(string $value): array
	{
		$response = $this->api->get(self::PATH . '/by-identifier/' . rawurlencode($value));

		return $this->mapList($this->payload($response), [Animal::class, 'fromArray']);
	}

	/**
	 * Lists the animals of an owner found by exact email or phone.
	 *
	 * @return list<Animal>
	 */
	public function findByOwner(string $emailOrPhone): array
	{
		if (trim($emailOrPhone) === '') {
			throw new InvalidArgumentException('emailOrPhone must not be empty.');
		}

		$response = $this->api->get(self::PATH . '/by-owner', ['email_or_phone' => $emailOrPhone]);

		return $this->mapList($this->payload($response), [Animal::class, 'fromArray']);
	}

	/**
	 * Partially updates an animal (nickname, color, sterilization_status, deceased).
	 * Requires being the owner or a vet with an active relation to the animal.
	 *
	 * @param array<string, mixed> $fields
	 */
	public function update(string $id, array $fields, ?string $idempotencyKey = null): void
	{
		if ($fields === []) {
			throw new InvalidArgumentException('update() requires at least one field to change.');
		}

		$this->api->patch(self::PATH . '/' . rawurlencode($id), $fields, $idempotencyKey);
	}
}
