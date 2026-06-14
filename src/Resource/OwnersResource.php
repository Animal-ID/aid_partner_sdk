<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Resource;

use AnimalId\PartnerSdk\Exception\InvalidArgumentException;
use AnimalId\PartnerSdk\Model\Owner;

/**
 * /v1/partner/owners — create and look up animal owners.
 */
final class OwnersResource extends AbstractResource
{
	const PATH = '/v1/partner/owners';

	/**
	 * Registers an owner (idempotent by email/phone — an existing owner is resolved).
	 *
	 * Required: one of email/phone, and consent.account_creation = true.
	 *
	 * @param array<string, mixed> $owner e.g. ['email' => ..., 'consent' => ['account_creation' => true]]
	 * @param string|null $idempotencyKey Your own key for safe retries; auto-generated when null.
	 */
	public function create(array $owner, ?string $idempotencyKey = null): Owner
	{
		$response = $this->api->post(self::PATH, $owner, $idempotencyKey);

		return Owner::fromArray($this->unwrapSingle($this->payload($response)));
	}

	/**
	 * Finds an owner by exact email or phone (single field; email detected by format).
	 *
	 * @throws \AnimalId\PartnerSdk\Exception\NotFoundException When no owner matches.
	 */
	public function search(string $emailOrPhone): Owner
	{
		if (trim($emailOrPhone) === '') {
			throw new InvalidArgumentException('emailOrPhone must not be empty.');
		}

		$response = $this->api->get(self::PATH . '/search', ['email_or_phone' => $emailOrPhone]);

		return Owner::fromArray($this->unwrapSingle($this->payload($response)));
	}
}
