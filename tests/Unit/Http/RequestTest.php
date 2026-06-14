<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Tests\Unit\Http;

use AnimalId\PartnerSdk\Http\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function testExposesAllParts(): void
    {
        $request = new Request(
            'post',
            'https://gw.example.test/v1/partner/owners',
            ['X-Eternity-App-Id' => 'app'],
            '{"email":"jane@example.com"}'
        );

        self::assertSame('POST', $request->getMethod(), 'method is normalized to upper case');
        self::assertSame('https://gw.example.test/v1/partner/owners', $request->getUrl());
        self::assertSame(['X-Eternity-App-Id' => 'app'], $request->getHeaders());
        self::assertSame('{"email":"jane@example.com"}', $request->getBody());
        self::assertNull($request->getMultipart());
    }

    public function testHeaderLookupIsCaseInsensitive(): void
    {
        $request = new Request('GET', 'https://gw.example.test/x', ['X-Eternity-App-Id' => 'app']);

        self::assertSame('app', $request->getHeader('x-eternity-app-id'));
        self::assertNull($request->getHeader('x-missing'));
    }

    public function testCarriesMultipartFields(): void
    {
        $multipart = ['kind' => 'avatar'];
        $request = new Request('POST', 'https://gw.example.test/x', [], null, $multipart);

        self::assertNull($request->getBody());
        self::assertSame($multipart, $request->getMultipart());
    }
}
