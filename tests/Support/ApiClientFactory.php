<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Tests\Support;

use AnimalId\PartnerSdk\Auth\IdempotencyKeyGenerator;
use AnimalId\PartnerSdk\Auth\RequestSigner;
use AnimalId\PartnerSdk\Config;
use AnimalId\PartnerSdk\Http\ApiClient;

/**
 * Builds an ApiClient on top of a FakeHttpClient with deterministic time.
 */
final class ApiClientFactory
{
    const APP_ID = 'aid_app_test';
    const PUBLIC_KEY = 'pk_test';
    const PRIVATE_KEY = 'sk_test_secret';
    const BASE_URL = 'https://gw.example.test';
    const FROZEN_TIME = 1748592000;

    public static function create(FakeHttpClient $httpClient, array $configOptions = []): ApiClient
    {
        $config = new Config(
            self::APP_ID,
            self::PUBLIC_KEY,
            self::PRIVATE_KEY,
            self::BASE_URL,
            $configOptions
        );

        return new ApiClient(
            $config,
            $httpClient,
            new RequestSigner(self::PRIVATE_KEY),
            new IdempotencyKeyGenerator(),
            static function (): int {
                return self::FROZEN_TIME;
            }
        );
    }
}
