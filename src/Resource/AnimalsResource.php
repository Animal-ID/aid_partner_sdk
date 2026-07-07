<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Resource;

use AnimalId\PartnerSdk\Exception\InvalidArgumentException;
use AnimalId\PartnerSdk\Model\Animal;
use AnimalId\PartnerSdk\Model\AnimalAccessRequest;

/**
 * /v1/partner/animals — register, look up and update animals, and request access to them.
 *
 * Lookup methods accept an optional `$expand` list (see {@see EXPAND_OWNERS}) that embeds extra
 * objects into each animal card via the X-Eternity-Expand header.
 */
final class AnimalsResource extends AbstractResource
{
	const PATH = '/v1/partner/animals';

	const IDENTIFIER_MICROCHIP = 'microchip';
	const IDENTIFIER_QR_TAG = 'qr_tag';

	/** Expand key: embed the animal's owners (owners[] with is_main_owner) into each card. */
	const EXPAND_OWNERS = 'owners';

	/**
	 * Registers an animal and returns its public id (NanoID).
	 *
	 * Required: species, nickname, is_microchip (+ microchip when is_microchip is true).
	 *
	 * Optionally attach owners via `owners[]` (the first becomes the main owner). Each entry is
	 * either `['public_id' => 'V1StGXR8...']` to attach an existing owner (from {@see OwnersResource::search()}),
	 * or inline `['email' => ..., 'consent' => ['account_creation' => true]]` to register a new one.
	 * `public_id` attachment requires API version >= 2026-07-04, which this SDK sends by default.
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

	/**
	 * @param list<string> $expand Expand keys to embed (e.g. [AnimalsResource::EXPAND_OWNERS]).
	 */
	public function get(string $id, array $expand = []): Animal
	{
		$response = $this->api->get(
			self::PATH . '/' . rawurlencode($id),
			[],
			$this->expandHeaders($expand)
		);

		return Animal::fromArray($this->unwrapSingle($this->payload($response)));
	}

	/**
	 * Looks up animals by a specific identifier type.
	 *
	 * @param string $type One of the IDENTIFIER_* constants (microchip, qr_tag).
	 * @param list<string> $expand Expand keys to embed.
	 *
	 * @return list<Animal> Usually one element; a value may resolve to several.
	 */
	public function findByIdentifier(string $type, string $value, array $expand = []): array
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
			self::PATH . '/by-identifier/' . rawurlencode($type) . '/' . rawurlencode($value),
			[],
			$this->expandHeaders($expand)
		);

		return $this->mapList($this->payload($response), [Animal::class, 'fromArray']);
	}

	/**
	 * Looks up animals by identifier value across microchip and qr_tag.
	 *
	 * @param list<string> $expand Expand keys to embed.
	 *
	 * @return list<Animal>
	 */
	public function findByAnyIdentifier(string $value, array $expand = []): array
	{
		$response = $this->api->get(
			self::PATH . '/by-identifier/' . rawurlencode($value),
			[],
			$this->expandHeaders($expand)
		);

		return $this->mapList($this->payload($response), [Animal::class, 'fromArray']);
	}

	/**
	 * Lists the animals of an owner found by exact email or phone.
	 *
	 * @param list<string> $expand Expand keys to embed.
	 *
	 * @return list<Animal>
	 */
	public function findByOwner(string $emailOrPhone, array $expand = []): array
	{
		if (trim($emailOrPhone) === '') {
			throw new InvalidArgumentException('emailOrPhone must not be empty.');
		}

		$response = $this->api->get(
			self::PATH . '/by-owner',
			['email_or_phone' => $emailOrPhone],
			$this->expandHeaders($expand)
		);

		return $this->mapList($this->payload($response), [Animal::class, 'fromArray']);
	}

	/**
	 * Partially updates an animal (nickname, color, sterilization_status, deceased).
	 *
	 * Requires edit access: be the owner, or a vet with an active relation to the animal. Without
	 * access the API answers 403 — request it with {@see requestAccess()} and retry once the owner
	 * approves.
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

	/**
	 * Asks the owner for access to an animal you cannot yet edit.
	 *
	 * Returns the resulting state: `granted` (you already had access — nothing was created),
	 * `pending` (the owner was notified and must approve), or `denied`. While a request is active
	 * you must wait `retryAfterSeconds` before re-requesting the same animal. Subscribe to the
	 * `animal_access.approved` / `animal_access.denied` webhooks to learn the owner's decision.
	 *
	 * @param string|null $idempotencyKey Your own key for safe retries; auto-generated when null.
	 */
	public function requestAccess(string $id, ?string $idempotencyKey = null): AnimalAccessRequest
	{
		$response = $this->api->post(
			self::PATH . '/' . rawurlencode($id) . '/access-request',
			[],
			$idempotencyKey
		);

		return AnimalAccessRequest::fromArray($this->unwrapSingle($this->payload($response)));
	}

	/**
	 * Current access state for an animal: granted / pending / denied / none.
	 */
	public function accessStatus(string $id): AnimalAccessRequest
	{
		$response = $this->api->get(self::PATH . '/' . rawurlencode($id) . '/access-request');

		return AnimalAccessRequest::fromArray($this->unwrapSingle($this->payload($response)));
	}

	/**
	 * Builds the X-Eternity-Expand header from a list of expand keys; empty when none requested.
	 *
	 * @param list<string> $expand
	 *
	 * @return array<string, string>
	 */
	private function expandHeaders(array $expand): array
	{
		$keys = array_values(array_filter($expand, static function ($key): bool {
			return is_string($key) && $key !== '';
		}));

		if ($keys === []) {
			return [];
		}

		return ['X-Eternity-Expand' => (string)json_encode($keys)];
	}
}
