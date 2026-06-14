<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Tests\Unit\Model;

use AnimalId\PartnerSdk\Model\Animal;
use AnimalId\PartnerSdk\Model\DictionaryItem;
use AnimalId\PartnerSdk\Model\DictionarySet;
use AnimalId\PartnerSdk\Model\Owner;
use AnimalId\PartnerSdk\Model\Photo;
use AnimalId\PartnerSdk\Model\Procedure;
use AnimalId\PartnerSdk\Model\ProcedureBatch;
use PHPUnit\Framework\TestCase;

final class ModelsTest extends TestCase
{
    public function testOwnerDefaultsForMissingKeys(): void
    {
        $owner = Owner::fromArray([]);

        self::assertNull($owner->getUserGid());
        self::assertFalse($owner->hasAccount());
        self::assertNull($owner->getEmail());
        self::assertNull($owner->getPhone());
        self::assertNull($owner->getDisplayHint());
        self::assertNull($owner->getLanguage());
        self::assertNull($owner->getCountryId());
        self::assertSame([], $owner->toArray());
    }

    public function testOwnerKeepsRawPayloadIncludingUnknownFields(): void
    {
        $data = ['user_gid' => 1, 'future_field' => 'x'];

        self::assertSame($data, Owner::fromArray($data)->toArray());
    }

    public function testAnimalDefaultsForMissingKeys(): void
    {
        $animal = Animal::fromArray(['id' => 'a1']);

        self::assertSame('a1', $animal->getId());
        self::assertNull($animal->getSpecies());
        self::assertNull($animal->getSterilizationStatus());
        self::assertFalse($animal->isDeceased());
        self::assertFalse($animal->isLost());
    }

    public function testAnimalLostAndDeceasedFlags(): void
    {
        $animal = Animal::fromArray([
            'id' => 'a1',
            'lost_status' => 'active',
            'deceased' => true,
            'died_at' => '2026-06-01',
        ]);

        self::assertTrue($animal->isLost());
        self::assertSame('active', $animal->getLostStatus());
        self::assertTrue($animal->isDeceased());
        self::assertSame('2026-06-01', $animal->getDiedAt());
    }

    public function testProcedureNormalizesBothPayloadShapes(): void
    {
        $fromPost = Procedure::fromArray([
            'id' => 1,
            'appointment_id' => 7,
            'procedure_type_id' => 20,
            'performed_at' => 1748592000,
            'extra_fields' => ['vaccine_name' => 'Rabisin'],
        ]);
        $fromGet = Procedure::fromArray([
            'id' => 1,
            'visit_id' => 7,
            'type' => 20,
            'occurred_at' => '2026-05-30T08:00:00+00:00',
            'type_specific_payload' => ['vaccine_name' => 'Rabisin'],
        ]);

        self::assertSame(7, $fromPost->getAppointmentId());
        self::assertSame(7, $fromGet->getAppointmentId());
        self::assertSame(20, $fromPost->getType());
        self::assertSame(20, $fromGet->getType());
        self::assertSame(1748592000, $fromPost->getOccurredAt());
        self::assertSame('2026-05-30T08:00:00+00:00', $fromGet->getOccurredAt());
        self::assertSame(['vaccine_name' => 'Rabisin'], $fromPost->getTypeSpecificPayload());
        self::assertSame(['vaccine_name' => 'Rabisin'], $fromGet->getTypeSpecificPayload());
    }

    public function testProcedureDefaults(): void
    {
        $procedure = Procedure::fromArray([]);

        self::assertSame(0, $procedure->getId());
        self::assertNull($procedure->getAnimalId());
        self::assertNull($procedure->getAppointmentId());
        self::assertNull($procedure->getType());
        self::assertNull($procedure->getOccurredAt());
        self::assertNull($procedure->getSummary());
        self::assertNull($procedure->getRevaccinationDate());
        self::assertSame([], $procedure->getTypeSpecificPayload());
        self::assertSame([], $procedure->toArray());
    }

    public function testProcedureBatchSkipsMalformedEntries(): void
    {
        $batch = ProcedureBatch::fromArray([
            'appointment_id' => 5,
            'procedures' => [['id' => 1], 'garbage', ['id' => 2]],
        ]);

        self::assertSame(5, $batch->getAppointmentId());
        self::assertCount(2, $batch->getProcedures());
        self::assertSame(2, $batch->getProcedures()[1]->getId());
    }

    public function testPhotoMapsIdAndKeepsRaw(): void
    {
        $photo = Photo::fromArray(['id' => '33015', 'url' => 'https://cdn.example/x.jpg']);

        self::assertSame(33015, $photo->getId());
        self::assertSame(['id' => '33015', 'url' => 'https://cdn.example/x.jpg'], $photo->toArray());
    }

    public function testDictionaryItemNameFallbacks(): void
    {
        $item = DictionaryItem::fromArray(['code' => 3, 'names' => ['uk' => 'Собаки', 'en' => 'Dogs']]);
        self::assertSame('Собаки', $item->getName('uk'));
        self::assertSame('Dogs', $item->getName('de'), 'falls back to English');

        $noEnglish = DictionaryItem::fromArray(['code' => 3, 'names' => ['uk' => 'Собаки']]);
        self::assertSame('Собаки', $noEnglish->getName('de'), 'falls back to any available locale');

        $empty = DictionaryItem::fromArray(['code' => 3]);
        self::assertNull($empty->getName('uk'));
        self::assertSame([], $empty->getNames());
    }

    public function testDictionaryItemLanguageExtras(): void
    {
        $language = DictionaryItem::fromArray(['code' => 'uk', 'native' => 'Українська']);

        self::assertSame('uk', $language->getCode());
        self::assertSame('Українська', $language->getNative());
        self::assertNull($language->getAlpha2());
    }

    public function testDictionarySetFromResponseToleratesMissingMetadata(): void
    {
        $set = DictionarySet::fromResponse(['payload' => [['key' => 'sex', 'items' => []]]]);

        self::assertNotNull($set->get('sex'));
        self::assertNull($set->getEtag());
        self::assertNull($set->getGeneratedAt());
        self::assertSame([], $set->getLanguages());
        self::assertFalse($set->isNotModified());
    }

    public function testDictionaryFindByCodeComparesLoosely(): void
    {
        $set = DictionarySet::fromResponse(['payload' => [
            ['key' => 'species', 'items' => [['code' => 3, 'names' => ['en' => 'Dogs']]]],
        ]]);
        $species = $set->get('species');

        self::assertNotNull($species->findByCode('3'), 'string code must match int code');
        self::assertNotNull($species->findByCode(3));
        self::assertNull($species->findByCode(99));
    }
}
