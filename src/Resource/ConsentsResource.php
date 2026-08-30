<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Resource;

use AnimalId\PartnerSdk\Exception\InvalidArgumentException;
use AnimalId\PartnerSdk\Model\ConsentRequest;

/**
 * /v1/platform/consents — permission a partner has to be given rather than take.
 *
 * The person decides in their own Animal ID cabinet, under their own login; there is no endpoint
 * that answers on their behalf, and that is the whole value of the record.
 */
final class ConsentsResource extends AbstractResource
{
	const PATH = '/v1/platform/consents';

	/**
	 * Asks a doctor to let you hold credentials that act as them.
	 *
	 * Only the doctor can allow this — not their clinic, not you — because the key signs as that
	 * human. Once approved, collect the key with
	 * {@see DoctorsResource::credentials()}.
	 */
	public function requestKeyHandover(string $doctorPublicId): ConsentRequest
	{
		return $this->request(ConsentRequest::KIND_KEY_HANDOVER, $doctorPublicId);
	}

	/**
	 * Asks a clinic's director to let you seat a doctor there.
	 *
	 * Needed only for a clinic you did not provision — your own clinics need no permission.
	 * Revoking it later stops you seating **new** doctors; those already seated stay, because they
	 * are members of that clinic now and are removed the ordinary way.
	 */
	public function requestClinicMembership(string $doctorPublicId, string $clinicPublicId): ConsentRequest
	{
		return $this->request(ConsentRequest::KIND_CLINIC_MEMBERSHIP, $doctorPublicId, $clinicPublicId);
	}

	/**
	 * Raises a permission request.
	 *
	 * Asking twice does not create two: an open request is returned as it stands, so a retry — or a
	 * user tapping twice — cannot pester the person on the other side.
	 *
	 * @param string $kind One of the ConsentRequest::KIND_* constants.
	 * @param string $clinicPublicId Required for clinic_membership.
	 *
	 * @throws \AnimalId\PartnerSdk\Exception\ConflictException When nobody can decide it, for
	 *         example a clinic with no director.
	 */
	public function request(string $kind, string $doctorPublicId, ?string $clinicPublicId = null): ConsentRequest
	{
		if (!in_array($kind, [ConsentRequest::KIND_KEY_HANDOVER, ConsentRequest::KIND_CLINIC_MEMBERSHIP], true)) {
			throw new InvalidArgumentException(sprintf('Unknown consent kind "%s".', $kind));
		}
		if (trim($doctorPublicId) === '') {
			throw new InvalidArgumentException('doctorPublicId must not be empty.');
		}
		if ($kind === ConsentRequest::KIND_CLINIC_MEMBERSHIP && ($clinicPublicId === null || trim($clinicPublicId) === '')) {
			throw new InvalidArgumentException('clinicPublicId is required for a clinic_membership request.');
		}

		$body = ['kind' => $kind, 'doctor_public_id' => $doctorPublicId];
		if ($clinicPublicId !== null && trim($clinicPublicId) !== '') {
			$body['clinic_public_id'] = $clinicPublicId;
		}

		$response = $this->api->post(self::PATH, $body);

		return ConsentRequest::fromArray($this->unwrapSingle($this->payload($response)));
	}

	/**
	 * Where a request stands now.
	 *
	 * Polling is the fallback, not the plan: subscribe to the `consent.*` webhooks and you are told
	 * instead — including about a revocation, which can land months later and which polling would
	 * only reveal on your next failed call.
	 *
	 * @throws \AnimalId\PartnerSdk\Exception\NotFoundException When no such request exists, or it
	 *         belongs to another partner.
	 */
	public function status(string $publicId): ConsentRequest
	{
		if (trim($publicId) === '') {
			throw new InvalidArgumentException('publicId must not be empty.');
		}

		$response = $this->api->get(self::PATH . '/' . rawurlencode($publicId));

		return ConsentRequest::fromArray($this->unwrapSingle($this->payload($response)));
	}
}
