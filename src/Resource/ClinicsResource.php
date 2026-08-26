<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Resource;

use AnimalId\PartnerSdk\Exception\InvalidArgumentException;
use AnimalId\PartnerSdk\Model\Clinic;

/**
 * /v1/platform/organizations — find a clinic that already exists, or create one.
 *
 * Search first. A clinic already on Animal ID has a director, a history and possibly patients;
 * provisioning a second copy of it splits both.
 */
final class ClinicsResource extends AbstractResource
{
	const PATH = '/v1/platform/organizations';

	/**
	 * Clinics matching a name or address fragment.
	 *
	 * Scoped to what you may see: published clinics plus your own. Another partner's provisioned
	 * clinics are never returned.
	 *
	 * @param string $query At least 2 characters.
	 * @param int|null $limit Up to 50 (default 20 server-side).
	 *
	 * @return list<Clinic> Empty when nothing matches.
	 */
	public function search(string $query, ?int $limit = null): array
	{
		if (mb_strlen(trim($query)) < 2) {
			throw new InvalidArgumentException('query must be at least 2 characters.');
		}

		$params = ['query' => $query];
		if ($limit !== null) {
			$params['limit'] = $limit;
		}

		$response = $this->api->get(self::PATH, $params);

		return $this->mapList($this->payload($response), static function (array $item): Clinic {
			return Clinic::fromArray($item);
		});
	}

	/**
	 * Creates a clinic, or returns the one you already have for this `external_org_id`.
	 *
	 * Send a **stable** `external_org_id`: a repeated call with the same value resolves to the same
	 * clinic instead of making another, which is what makes a retried signup safe.
	 *
	 * A clinic cannot exist without a director, and a doctor may direct only one clinic — naming
	 * someone who already directs elsewhere answers 409.
	 *
	 * @param array<string, mixed> $clinic Requires external_org_id, name and director_public_id;
	 *                                     accepts email, phone, website, address, country_id,
	 *                                     description, lat, lng.
	 *
	 * @throws \AnimalId\PartnerSdk\Exception\ConflictException When the named director already
	 *                                                          directs another clinic.
	 */
	public function provision(array $clinic): Clinic
	{
		foreach (['external_org_id', 'name', 'director_public_id'] as $required) {
			if (!isset($clinic[$required]) || $clinic[$required] === '') {
				throw new InvalidArgumentException(sprintf('%s is required.', $required));
			}
		}

		$response = $this->api->post(self::PATH, $clinic);

		return Clinic::fromArray($this->unwrapSingle($this->payload($response)));
	}
}
