<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Tests\Unit\Resource;

use AnimalId\PartnerSdk\Exception\InvalidArgumentException;
use AnimalId\PartnerSdk\Exception\NotFoundException;
use AnimalId\PartnerSdk\Resource\OwnersResource;
use AnimalId\PartnerSdk\Tests\Support\ApiClientFactory;
use AnimalId\PartnerSdk\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class OwnersResourceTest extends TestCase
{
    /** @var FakeHttpClient */
    private $http;

    /** @var OwnersResource */
    private $owners;

    protected function setUp(): void
    {
        $this->http = new FakeHttpClient();
        $this->owners = new OwnersResource(ApiClientFactory::create($this->http));
    }

    public function testCreatePostsBodyAndMapsOwner(): void
    {
        $this->http->queueJson(201, [
            'payload' => [
                'user_gid' => 90231,
                'has_account' => true,
                'email' => 'jane@example.com',
                'phone' => null,
                'display_hint' => 'Ол*** К.',
                'language' => 'uk',
                'country_id' => 804,
            ],
        ]);

        $input = [
            'email' => 'jane@example.com',
            'first_name' => 'Jane',
            'consent' => ['account_creation' => true],
        ];
        $owner = $this->owners->create($input, 'retry-key-7');

        $request = $this->http->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame(ApiClientFactory::BASE_URL . '/v1/partner/owners', $request->getUrl());
        self::assertSame($input, json_decode((string) $request->getBody(), true));
        self::assertSame('retry-key-7', $request->getHeader('X-Eternity-Idempotency-Key'));

        self::assertSame(90231, $owner->getUserGid());
        self::assertTrue($owner->hasAccount());
        self::assertSame('jane@example.com', $owner->getEmail());
        self::assertNull($owner->getPhone());
        self::assertSame('Ол*** К.', $owner->getDisplayHint());
        self::assertSame('uk', $owner->getLanguage());
        self::assertSame(804, $owner->getCountryId());
    }

    public function testCreateUnwrapsSingleElementPayloadArray(): void
    {
        // The API may wrap single resources in a one-element payload array.
        $this->http->queueJson(201, ['payload' => [['user_gid' => 7, 'has_account' => false]]]);

        $owner = $this->owners->create(['phone' => '+380681234567', 'consent' => ['account_creation' => true]]);

        self::assertSame(7, $owner->getUserGid());
        self::assertFalse($owner->hasAccount());
    }

    public function testSearchSendsQueryAndMapsOwner(): void
    {
        $this->http->queueJson(200, ['payload' => ['user_gid' => 90231, 'public_id' => 'V1StGXR8Z5jd', 'has_account' => true]]);

        $owner = $this->owners->search('jane@example.com');

        $request = $this->http->lastRequest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame(
            ApiClientFactory::BASE_URL . '/v1/partner/owners/search?email_or_phone=' . urlencode('jane@example.com'),
            $request->getUrl()
        );
        self::assertSame(90231, $owner->getUserGid());
        self::assertSame('V1StGXR8Z5jd', $owner->getPublicId());
    }

    public function testSearchPropagatesNotFound(): void
    {
        $this->http->queueJson(404, ['message' => 'Owner not found']);

        $this->expectException(NotFoundException::class);

        $this->owners->search('absent@example.com');
    }

    public function testSearchRejectsEmptyInput(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->owners->search('   ');
    }
}
