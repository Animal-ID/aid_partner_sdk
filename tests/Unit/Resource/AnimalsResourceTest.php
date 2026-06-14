<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Tests\Unit\Resource;

use AnimalId\PartnerSdk\Exception\InvalidArgumentException;
use AnimalId\PartnerSdk\Exception\NotFoundException;
use AnimalId\PartnerSdk\Resource\AnimalsResource;
use AnimalId\PartnerSdk\Tests\Support\ApiClientFactory;
use AnimalId\PartnerSdk\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class AnimalsResourceTest extends TestCase
{
    /** @var FakeHttpClient */
    private $http;

    /** @var AnimalsResource */
    private $animals;

    protected function setUp(): void
    {
        $this->http = new FakeHttpClient();
        $this->animals = new AnimalsResource(ApiClientFactory::create($this->http));
    }

    public function testCreateReturnsTheNewAnimalId(): void
    {
        $this->http->queueJson(201, ['payload' => ['id' => '8xK3pQzVnB7rL2qF']]);

        $input = [
            'species' => 3,
            'is_microchip' => true,
            'microchip' => '900263000123456',
            'nickname' => 'Барсік',
            'owners' => [['user_gid' => 90231]],
        ];
        $id = $this->animals->create($input);

        $request = $this->http->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame(ApiClientFactory::BASE_URL . '/v1/partner/animals', $request->getUrl());
        self::assertSame($input, json_decode((string) $request->getBody(), true));
        self::assertSame('8xK3pQzVnB7rL2qF', $id);
    }

    public function testCreateUnwrapsSingleElementPayloadArray(): void
    {
        $this->http->queueJson(201, ['payload' => [['id' => 'nano-1']]]);

        self::assertSame('nano-1', $this->animals->create(['species' => 3, 'is_microchip' => false, 'nickname' => 'X']));
    }

    public function testGetMapsTheFullAnimalCard(): void
    {
        $this->http->queueJson(200, [
            'payload' => [
                'id' => '8xK3pQzVnB7rL2qF',
                'species' => 3,
                'breed' => 'Labrador',
                'color' => 'black',
                'gender_id' => 1,
                'nickname' => 'Барсік',
                'microchip' => '900263000123456',
                'qr_tag' => null,
                'dob' => '2022-03-01',
                'register_date' => '2026-05-30',
                'sterilization_status' => true,
                'lost_status' => null,
                'deceased' => false,
                'died_at' => null,
                'status' => 1,
            ],
        ]);

        $animal = $this->animals->get('8xK3pQzVnB7rL2qF');

        self::assertSame(
            ApiClientFactory::BASE_URL . '/v1/partner/animals/8xK3pQzVnB7rL2qF',
            $this->http->lastRequest()->getUrl()
        );
        self::assertSame('8xK3pQzVnB7rL2qF', $animal->getId());
        self::assertSame(3, $animal->getSpecies());
        self::assertSame('Labrador', $animal->getBreed());
        self::assertSame('black', $animal->getColor());
        self::assertSame(1, $animal->getGenderId());
        self::assertSame('Барсік', $animal->getNickname());
        self::assertSame('900263000123456', $animal->getMicrochip());
        self::assertNull($animal->getQrTag());
        self::assertSame('2022-03-01', $animal->getDob());
        self::assertSame('2026-05-30', $animal->getRegisterDate());
        self::assertTrue($animal->getSterilizationStatus());
        self::assertNull($animal->getLostStatus());
        self::assertFalse($animal->isLost());
        self::assertFalse($animal->isDeceased());
        self::assertNull($animal->getDiedAt());
        self::assertSame(1, $animal->getStatus());
    }

    public function testGetPropagatesNotFound(): void
    {
        $this->http->queueJson(404, ['message' => 'Animal not found']);

        $this->expectException(NotFoundException::class);

        $this->animals->get('missing');
    }

    public function testFindByIdentifierBuildsPathAndMapsList(): void
    {
        $this->http->queueJson(200, ['payload' => [
            ['id' => 'a1', 'nickname' => 'Барсік', 'lost_status' => 'active'],
            ['id' => 'a2', 'nickname' => 'Мурчик'],
        ]]);

        $found = $this->animals->findByIdentifier(AnimalsResource::IDENTIFIER_MICROCHIP, '900263000123456');

        self::assertSame(
            ApiClientFactory::BASE_URL . '/v1/partner/animals/by-identifier/microchip/900263000123456',
            $this->http->lastRequest()->getUrl()
        );
        self::assertCount(2, $found);
        self::assertSame('a1', $found[0]->getId());
        self::assertTrue($found[0]->isLost());
        self::assertFalse($found[1]->isLost());
    }

    public function testFindByIdentifierEncodesPathSegments(): void
    {
        $this->http->queueJson(200, ['payload' => []]);

        $this->animals->findByIdentifier(AnimalsResource::IDENTIFIER_QR_TAG, 'QR/2026 01');

        self::assertSame(
            ApiClientFactory::BASE_URL . '/v1/partner/animals/by-identifier/qr_tag/QR%2F2026%2001',
            $this->http->lastRequest()->getUrl()
        );
    }

    public function testFindByIdentifierRejectsUnknownType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->animals->findByIdentifier('tattoo', 'TAT-001');
    }

    public function testFindByAnyIdentifierSearchesAcrossTypes(): void
    {
        $this->http->queueJson(200, ['payload' => [['id' => 'a1', 'microchip' => '900263000123456']]]);

        $found = $this->animals->findByAnyIdentifier('900263000123456');

        self::assertSame(
            ApiClientFactory::BASE_URL . '/v1/partner/animals/by-identifier/900263000123456',
            $this->http->lastRequest()->getUrl()
        );
        self::assertCount(1, $found);
        self::assertSame('a1', $found[0]->getId());
    }

    public function testFindByOwnerSendsQuery(): void
    {
        $this->http->queueJson(200, ['payload' => [['id' => 'a1', 'species' => 3]]]);

        $found = $this->animals->findByOwner('+380681234567');

        self::assertSame(
            ApiClientFactory::BASE_URL . '/v1/partner/animals/by-owner?email_or_phone=' . urlencode('+380681234567'),
            $this->http->lastRequest()->getUrl()
        );
        self::assertCount(1, $found);
    }

    public function testFindByOwnerRejectsEmptyInput(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->animals->findByOwner('');
    }

    public function testUpdateSendsPatch(): void
    {
        $this->http->queueJson(204, []);

        $this->animals->update('8xK3pQzVnB7rL2qF', ['nickname' => 'Барсік', 'deceased' => false], 'patch-key');

        $request = $this->http->lastRequest();
        self::assertSame('PATCH', $request->getMethod());
        self::assertSame(ApiClientFactory::BASE_URL . '/v1/partner/animals/8xK3pQzVnB7rL2qF', $request->getUrl());
        self::assertSame(
            ['nickname' => 'Барсік', 'deceased' => false],
            json_decode((string) $request->getBody(), true)
        );
        self::assertSame('patch-key', $request->getHeader('X-Eternity-Idempotency-Key'));
    }

    public function testUpdateRejectsEmptyFieldSet(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->animals->update('8xK3pQzVnB7rL2qF', []);
    }
}
