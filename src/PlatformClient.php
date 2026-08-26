<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk;

use AnimalId\PartnerSdk\Auth\IdempotencyKeyGenerator;
use AnimalId\PartnerSdk\Auth\RequestSigner;
use AnimalId\PartnerSdk\Http\ApiClient;
use AnimalId\PartnerSdk\Http\CurlHttpClient;
use AnimalId\PartnerSdk\Http\HttpClientInterface;
use AnimalId\PartnerSdk\Resource\ClinicsResource;
use AnimalId\PartnerSdk\Resource\ConsentsResource;
use AnimalId\PartnerSdk\Resource\DoctorsResource;

/**
 * Entry point of the **provisioning plane**: clinics, doctors and the permissions you have to be
 * given rather than take.
 *
 * Deliberately a separate client from {@see PartnerClient}, because it is a separate key. The two
 * planes resolve different application types on the server, so a platform key answers 401 on
 * `/v1/partner/` and a doctor's key answers 401 here — that is the separation working, not a
 * misconfiguration. Modelling them as one client with one Config would invite exactly that mistake.
 *
 * Your platform key is issued once, when your partner account is set up; it never reaches animal
 * data. The doctor keys it hands you do the actual work:
 *
 *     $platform = new PlatformClient(new Config($platformAppId, $publicKey, $privateKey));
 *
 *     $clinic = $platform->clinics()->provision([
 *         'external_org_id'    => 'crm-clinic-118',
 *         'name'               => 'Лапа',
 *         'director_public_id' => $director->getPublicId(),
 *     ]);
 *
 *     $doctor = $platform->doctors()->seat($clinic->getPublicId(), [
 *         'email'   => 'doctor@example.com',
 *         'consent' => ['account_creation' => true],
 *     ]);
 *
 *     // Store $doctor->getPrivateKey() now — it is never shown again.
 *     $vet = new PartnerClient($doctor->toConfig());
 */
final class PlatformClient
{
    /** @var ClinicsResource */
    private $clinics;

    /** @var DoctorsResource */
    private $doctors;

    /** @var ConsentsResource */
    private $consents;

    /**
     * @param Config $config Your PLATFORM credentials — not a doctor's.
     * @param HttpClientInterface|null $httpClient Custom transport (tests, PSR adapters);
     *                                             defaults to the built-in cURL client.
     */
    public function __construct(Config $config, ?HttpClientInterface $httpClient = null)
    {
        if ($httpClient === null) {
            $httpClient = new CurlHttpClient($config->getTimeout(), $config->getConnectTimeout());
        }

        $api = new ApiClient(
            $config,
            $httpClient,
            new RequestSigner($config->getPrivateKey()),
            new IdempotencyKeyGenerator()
        );

        $this->clinics = new ClinicsResource($api);
        $this->doctors = new DoctorsResource($api);
        $this->consents = new ConsentsResource($api);
    }

    /**
     * Find an existing clinic, or provision one.
     */
    public function clinics(): ClinicsResource
    {
        return $this->clinics;
    }

    /**
     * Seat doctors and collect the keys they sign the data plane with.
     */
    public function doctors(): DoctorsResource
    {
        return $this->doctors;
    }

    /**
     * Ask a doctor or a clinic's director for permission you cannot take.
     */
    public function consents(): ConsentsResource
    {
        return $this->consents;
    }
}
