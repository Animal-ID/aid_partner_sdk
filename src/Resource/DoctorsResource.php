<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Resource;

use AnimalId\PartnerSdk\Exception\InvalidArgumentException;
use AnimalId\PartnerSdk\Model\DoctorCredentials;

/**
 * /v1/platform/organizations/{clinic}/members — seat a doctor in a clinic and collect the key that
 * signs the data plane as them.
 *
 * A doctor is always seated in a clinic: a key that authenticates somebody with no clinic to act
 * for is worse than no key, because it looks like it works.
 */
final class DoctorsResource extends AbstractResource
{
	/**
	 * Creates the doctor (or matches an existing account by email/phone), seats them, and returns
	 * their credentials.
	 *
	 * `consent.account_creation` must be true: the credentials act as this person, so their
	 * agreement is required.
	 *
	 * **The private key comes back once.** Store it before you do anything else.
	 *
	 * @param string $clinicPublicId The clinic to seat them in.
	 * @param array<string, mixed> $doctor One of email/phone, plus consent; accepts first_name,
	 *                                     last_name, language, clinic_name, external_doctor_id.
	 *
	 * @throws \AnimalId\PartnerSdk\Exception\ConflictException When the doctor already holds a key
	 *         for this clinic, or the clinic is not one you provisioned and its director has not
	 *         approved you — raise a clinic_membership consent first.
	 */
	public function seat(string $clinicPublicId, array $doctor): DoctorCredentials
	{
		$this->assertPublicId($clinicPublicId, 'clinicPublicId');

		if (empty($doctor['email']) && empty($doctor['phone'])) {
			throw new InvalidArgumentException('One of email/phone is required.');
		}
		if (empty($doctor['consent']['account_creation'])) {
			throw new InvalidArgumentException(
				'consent.account_creation must be true — the credentials act as this person.'
			);
		}

		$response = $this->api->post($this->membersPath($clinicPublicId), $doctor);

		return DoctorCredentials::fromArray($this->unwrapSingle($this->payload($response)));
	}

	/**
	 * Collects credentials for a doctor who **already exists** and has agreed to the handover.
	 *
	 * Unlike {@see self::seat()} this creates nobody. It requires a standing `key_handover`
	 * approval from the doctor — ask for one through
	 * {@see ConsentsResource::requestKeyHandover()} and wait for the answer.
	 *
	 * The key you receive is held by you and is **separate from the doctor's own**: when they
	 * withdraw consent yours stops working and theirs does not.
	 *
	 * @throws \AnimalId\PartnerSdk\Exception\ConflictException When the doctor has not agreed, or
	 *         the clinic is not one you may act on. The message names which of the two.
	 */
	public function credentials(string $clinicPublicId, string $doctorPublicId): DoctorCredentials
	{
		$this->assertPublicId($clinicPublicId, 'clinicPublicId');
		$this->assertPublicId($doctorPublicId, 'doctorPublicId');

		$response = $this->api->post(
			$this->membersPath($clinicPublicId) . '/' . rawurlencode($doctorPublicId) . '/credentials',
			[]
		);

		return DoctorCredentials::fromArray($this->unwrapSingle($this->payload($response)));
	}

	private function membersPath(string $clinicPublicId): string
	{
		return ClinicsResource::PATH . '/' . rawurlencode($clinicPublicId) . '/members';
	}

	private function assertPublicId(string $value, string $name): void
	{
		if (trim($value) === '') {
			throw new InvalidArgumentException(sprintf('%s must not be empty.', $name));
		}
	}
}
