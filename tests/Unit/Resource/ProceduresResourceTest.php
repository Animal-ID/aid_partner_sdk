<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Tests\Unit\Resource;

use AnimalId\PartnerSdk\Exception\InvalidArgumentException;
use AnimalId\PartnerSdk\Exception\NotFoundException;
use AnimalId\PartnerSdk\Resource\ProceduresResource;
use AnimalId\PartnerSdk\Tests\Support\ApiClientFactory;
use AnimalId\PartnerSdk\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class ProceduresResourceTest extends TestCase
{
    /** @var FakeHttpClient */
    private $http;

    /** @var ProceduresResource */
    private $procedures;

    protected function setUp(): void
    {
        $this->http = new FakeHttpClient();
        $this->procedures = new ProceduresResource(ApiClientFactory::create($this->http));
    }

    public function testCreatePostsBatchAndMapsResult(): void
    {
        // POST returns the same partner card as GET (partner-api-docs example response).
        $this->http->queueJson(201, [
            'payload' => [
                'appointment_id' => 7741,
                'procedures' => [
                    [
                        'id' => 99001,
                        'animal_id' => '8xK3pQzVnB7rL2qF',
                        'visit_id' => 7741,
                        'type' => 10,
                        'occurred_at' => '2026-05-30T08:00:00+00:00',
                        'summary' => 'Annual shot',
                        'revaccination_date' => '2027-05-30',
                        'type_specific_payload' => ['vaccine_name' => 'Nobivac', 'batch_number' => 'A123'],
                    ],
                ],
            ],
        ]);

        $batch = [
            [
                'type' => ProceduresResource::TYPE_VACCINATION,
                'occurred_at' => '2026-05-30T08:00:00+00:00',
                'type_specific_payload' => ['vaccine_name' => 'Nobivac', 'batch_number' => 'A123'],
            ],
        ];
        $result = $this->procedures->create('8xK3pQzVnB7rL2qF', $batch);

        $request = $this->http->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame(
            ApiClientFactory::BASE_URL . '/v1/partner/animals/8xK3pQzVnB7rL2qF/procedures',
            $request->getUrl()
        );
        self::assertSame($batch, json_decode((string) $request->getBody(), true));

        self::assertSame(7741, $result->getAppointmentId());
        self::assertCount(1, $result->getProcedures());

        $procedure = $result->getProcedures()[0];
        self::assertSame(99001, $procedure->getId());
        self::assertSame('8xK3pQzVnB7rL2qF', $procedure->getAnimalId());
        self::assertSame(7741, $procedure->getAppointmentId(), 'visit_id is normalized to appointmentId');
        self::assertSame(10, $procedure->getType());
        self::assertSame('2026-05-30T08:00:00+00:00', $procedure->getOccurredAt());
        self::assertSame('Annual shot', $procedure->getSummary());
        self::assertSame('2027-05-30', $procedure->getRevaccinationDate());
        self::assertSame(
            ['vaccine_name' => 'Nobivac', 'batch_number' => 'A123'],
            $procedure->getTypeSpecificPayload()
        );
    }

    public function testCreateMapsLegacyInfoShapedResponse(): void
    {
        // Older gateways answered POST with the internal shape — still normalized.
        $this->http->queueJson(201, [
            'payload' => [
                'appointment_id' => 7741,
                'procedures' => [
                    [
                        'id' => 99001,
                        'animal_id' => '8xK3pQzVnB7rL2qF',
                        'appointment_id' => 7741,
                        'procedure_type_id' => 10,
                        'performed_at' => 1748592000,
                        'revaccination_date' => '2027-05-30',
                        'extra_fields' => ['vaccine_name' => 'Nobivac', 'batch_number' => 'A123'],
                    ],
                ],
            ],
        ]);

        $result = $this->procedures->create('8xK3pQzVnB7rL2qF', [
            [
                'type' => ProceduresResource::TYPE_VACCINATION,
                'occurred_at' => '2026-05-30T08:00:00+00:00',
            ],
        ]);

        $procedure = $result->getProcedures()[0];
        self::assertSame(10, $procedure->getType(), 'procedure_type_id is normalized to type');
        self::assertSame(1748592000, $procedure->getOccurredAt(), 'performed_at is normalized to occurredAt');
        self::assertSame(
            ['vaccine_name' => 'Nobivac', 'batch_number' => 'A123'],
            $procedure->getTypeSpecificPayload(),
            'extra_fields is normalized to typeSpecificPayload'
        );
    }

    public function testCreateAcceptsSingleProcedureObject(): void
    {
        $this->http->queueJson(201, ['payload' => ['appointment_id' => 1, 'procedures' => []]]);

        $single = [
            'type' => ProceduresResource::TYPE_TRANSPONDER_IDENTIFICATION,
            'occurred_at' => '2026-05-30T08:05:00+00:00',
            'type_specific_payload' => ['transponder_number' => '900263000123456'],
        ];
        $this->procedures->create('a1', $single);

        self::assertSame($single, json_decode((string) $this->http->lastRequest()->getBody(), true));
    }

    public function testCreateRejectsEmptyInput(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->procedures->create('a1', []);
    }

    public function testCreateRejectsOversizedBatch(): void
    {
        $batch = array_fill(0, 101, ['type' => 10, 'occurred_at' => '2026-05-30T08:00:00+00:00']);

        $this->expectException(InvalidArgumentException::class);

        $this->procedures->create('a1', $batch);
    }

    public function testListForAnimalSendsFiltersAndMapsGetShape(): void
    {
        // GET uses type / occurred_at / visit_id / type_specific_payload keys.
        $this->http->queueJson(200, ['payload' => [
            [
                'id' => 99001,
                'animal_id' => '8xK3pQzVnB7rL2qF',
                'visit_id' => 7741,
                'type' => 10,
                'occurred_at' => '2026-05-30T08:00:00+00:00',
                'summary' => null,
                'revaccination_date' => '2027-05-30',
                'type_specific_payload' => ['vaccine_name' => 'Nobivac'],
            ],
        ]]);

        $procedures = $this->procedures->listForAnimal(
            '8xK3pQzVnB7rL2qF',
            ProceduresResource::TYPE_VACCINATION,
            '2026-01-01T00:00:00+00:00',
            '2026-12-31T23:59:59+00:00'
        );

        $url = $this->http->lastRequest()->getUrl();
        self::assertStringContainsString('/v1/partner/animals/8xK3pQzVnB7rL2qF/procedures?', $url);
        self::assertStringContainsString('type=10', $url);
        self::assertStringContainsString('since=' . urlencode('2026-01-01T00:00:00+00:00'), $url);
        self::assertStringContainsString('until=' . urlencode('2026-12-31T23:59:59+00:00'), $url);

        self::assertCount(1, $procedures);
        $procedure = $procedures[0];
        self::assertSame(7741, $procedure->getAppointmentId(), 'visit_id is normalized to appointmentId');
        self::assertSame(10, $procedure->getType());
        self::assertSame('2026-05-30T08:00:00+00:00', $procedure->getOccurredAt());
        self::assertNull($procedure->getSummary());
        self::assertSame(['vaccine_name' => 'Nobivac'], $procedure->getTypeSpecificPayload());
    }

    public function testListForAnimalWithoutFiltersHasNoQueryString(): void
    {
        $this->http->queueJson(200, ['payload' => []]);

        $this->procedures->listForAnimal('a1');

        self::assertSame(
            ApiClientFactory::BASE_URL . '/v1/partner/animals/a1/procedures',
            $this->http->lastRequest()->getUrl()
        );
    }

    public function testGetFetchesSingleProcedure(): void
    {
        $this->http->queueJson(200, ['payload' => [
            'id' => 99001,
            'type' => 10,
            'occurred_at' => '2026-05-30T08:00:00+00:00',
            'visit_id' => 7741,
        ]]);

        $procedure = $this->procedures->get(99001);

        self::assertSame(
            ApiClientFactory::BASE_URL . '/v1/partner/procedures/99001',
            $this->http->lastRequest()->getUrl()
        );
        self::assertSame(99001, $procedure->getId());
        self::assertSame(10, $procedure->getType());
    }

    public function testGetPropagatesNotFound(): void
    {
        $this->http->queueJson(404, ['message' => 'Procedure not found']);

        $this->expectException(NotFoundException::class);

        $this->procedures->get(1);
    }
}
