<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Tests\Unit;

use AnimalId\PartnerSdk\Config;
use AnimalId\PartnerSdk\PartnerClient;
use AnimalId\PartnerSdk\Resource\AnimalsResource;
use AnimalId\PartnerSdk\Resource\DictionariesResource;
use AnimalId\PartnerSdk\Resource\OwnersResource;
use AnimalId\PartnerSdk\Resource\PhotosResource;
use AnimalId\PartnerSdk\Resource\ProceduresResource;
use AnimalId\PartnerSdk\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class PartnerClientTest extends TestCase
{
    public function testExposesAllResourceGroups(): void
    {
        $client = new PartnerClient(new Config('app', 'pk', 'sk'), new FakeHttpClient());

        self::assertInstanceOf(DictionariesResource::class, $client->dictionaries());
        self::assertInstanceOf(OwnersResource::class, $client->owners());
        self::assertInstanceOf(AnimalsResource::class, $client->animals());
        self::assertInstanceOf(ProceduresResource::class, $client->procedures());
        self::assertInstanceOf(PhotosResource::class, $client->photos());

        // Resources are wired once and reused.
        self::assertSame($client->owners(), $client->owners());
    }

    public function testEndToEndCallThroughInjectedTransport(): void
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, ['payload' => ['user_gid' => 42, 'has_account' => true]]);

        $client = new PartnerClient(
            new Config('app', 'pk', 'sk', 'https://gw.example.test'),
            $http
        );

        $owner = $client->owners()->search('jane@example.com');

        self::assertSame(42, $owner->getUserGid());

        $request = $http->lastRequest();
        self::assertSame('app', $request->getHeader('X-Eternity-App-Id'));
        self::assertSame('pk', $request->getHeader('X-Eternity-Public-Key'));
        self::assertNotNull($request->getHeader('X-Eternity-Signature'));
        self::assertNotNull($request->getHeader('X-Eternity-Timestamp'));
        self::assertStringStartsWith('https://gw.example.test/v1/partner/owners/search', $request->getUrl());
    }

    public function testDefaultsToCurlTransportWhenNoneInjected(): void
    {
        // Only verifies construction succeeds without an injected transport.
        $client = new PartnerClient(new Config('app', 'pk', 'sk'));

        self::assertInstanceOf(OwnersResource::class, $client->owners());
    }
}
