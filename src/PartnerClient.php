<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk;

use AnimalId\PartnerSdk\Auth\IdempotencyKeyGenerator;
use AnimalId\PartnerSdk\Auth\RequestSigner;
use AnimalId\PartnerSdk\Http\ApiClient;
use AnimalId\PartnerSdk\Http\CurlHttpClient;
use AnimalId\PartnerSdk\Http\HttpClientInterface;
use AnimalId\PartnerSdk\Resource\AnimalsResource;
use AnimalId\PartnerSdk\Resource\DictionariesResource;
use AnimalId\PartnerSdk\Resource\OwnersResource;
use AnimalId\PartnerSdk\Resource\PhotosResource;
use AnimalId\PartnerSdk\Resource\ProceduresResource;

/**
 * Entry point of the SDK: wires the transport, signing and resource groups.
 *
 *     $client = new PartnerClient(new Config($appId, $publicKey, $privateKey));
 *     $owner = $client->owners()->search('jane@example.com');
 */
final class PartnerClient
{
    /** @var DictionariesResource */
    private $dictionaries;

    /** @var OwnersResource */
    private $owners;

    /** @var AnimalsResource */
    private $animals;

    /** @var ProceduresResource */
    private $procedures;

    /** @var PhotosResource */
    private $photos;

    /**
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

        $this->dictionaries = new DictionariesResource($api);
        $this->owners = new OwnersResource($api);
        $this->animals = new AnimalsResource($api);
        $this->procedures = new ProceduresResource($api);
        $this->photos = new PhotosResource($api);
    }

    public function dictionaries(): DictionariesResource
    {
        return $this->dictionaries;
    }

    public function owners(): OwnersResource
    {
        return $this->owners;
    }

    public function animals(): AnimalsResource
    {
        return $this->animals;
    }

    public function procedures(): ProceduresResource
    {
        return $this->procedures;
    }

    public function photos(): PhotosResource
    {
        return $this->photos;
    }
}
