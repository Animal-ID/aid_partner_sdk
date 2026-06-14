<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Tests\Unit\Resource;

use AnimalId\PartnerSdk\Http\Response;
use AnimalId\PartnerSdk\Resource\DictionariesResource;
use AnimalId\PartnerSdk\Tests\Support\ApiClientFactory;
use AnimalId\PartnerSdk\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class DictionariesResourceTest extends TestCase
{
    /** @var FakeHttpClient */
    private $http;

    /** @var DictionariesResource */
    private $dictionaries;

    protected function setUp(): void
    {
        $this->http = new FakeHttpClient();
        $this->dictionaries = new DictionariesResource(ApiClientFactory::create($this->http));
    }

    public function testFetchesAndMapsDictionaries(): void
    {
        // Shape taken from the partner-api-docs example response.
        $this->http->queueJson(200, [
            'payload' => [
                [
                    'key' => 'species',
                    'items' => [
                        ['code' => 3, 'names' => ['uk' => 'Собаки', 'en' => 'Dogs']],
                        ['code' => 4, 'names' => ['uk' => 'Коти', 'en' => 'Cats']],
                    ],
                ],
                [
                    'key' => 'countries',
                    'items' => [
                        ['code' => '804', 'alpha2' => 'UA', 'alpha3' => 'UKR', 'names' => ['en' => 'Ukraine']],
                    ],
                ],
            ],
            'metadata' => [
                'etag' => 'W/"dict-abc"',
                'generated_at' => '2026-05-30T08:00:00+00:00',
                'languages' => ['uk', 'en'],
            ],
        ]);

        $set = $this->dictionaries->all();

        self::assertFalse($set->isNotModified());
        self::assertCount(2, $set->all());
        self::assertSame('W/"dict-abc"', $set->getEtag());
        self::assertSame('2026-05-30T08:00:00+00:00', $set->getGeneratedAt());
        self::assertSame(['uk', 'en'], $set->getLanguages());

        $species = $set->get('species');
        self::assertNotNull($species);
        self::assertCount(2, $species->getItems());
        self::assertSame('Собаки', $species->getItems()[0]->getName('uk'));

        $ukraine = $set->get('countries')->findByCode('804');
        self::assertNotNull($ukraine);
        self::assertSame('UA', $ukraine->getAlpha2());
        self::assertSame('UKR', $ukraine->getAlpha3());

        self::assertNull($set->get('missing'));
    }

    public function testBuildsQueryParameters(): void
    {
        $this->http->queueJson(200, ['payload' => []]);

        $this->dictionaries->all(['species', 'sex'], 'dog', 'uk');

        $url = $this->http->lastRequest()->getUrl();
        self::assertStringContainsString('include=' . urlencode('species,sex'), $url);
        self::assertStringContainsString('q=dog', $url);
        self::assertStringContainsString('lang=uk', $url);
    }

    public function testSendsIfNoneMatchAndHandles304(): void
    {
        $this->http->queue(new Response(304, ['ETag' => 'W/"dict-abc"']));

        $set = $this->dictionaries->all(null, null, null, 'W/"dict-abc"');

        self::assertSame('W/"dict-abc"', $this->http->lastRequest()->getHeader('If-None-Match'));
        self::assertTrue($set->isNotModified());
        self::assertSame('W/"dict-abc"', $set->getEtag());
        self::assertSame([], $set->all());
    }

    public function testFallsBackToRequestEtagWhen304HasNoHeader(): void
    {
        $this->http->queue(new Response(304));

        $set = $this->dictionaries->all(null, null, null, 'W/"dict-old"');

        self::assertTrue($set->isNotModified());
        self::assertSame('W/"dict-old"', $set->getEtag());
    }
}
