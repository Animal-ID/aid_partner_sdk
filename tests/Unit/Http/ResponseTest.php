<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Tests\Unit\Http;

use AnimalId\PartnerSdk\Exception\UnexpectedResponseException;
use AnimalId\PartnerSdk\Http\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testExposesStatusHeadersAndBody(): void
    {
        $response = new Response(201, ['Content-Type' => 'application/json'], '{"payload":{"id":1}}');

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('{"payload":{"id":1}}', $response->getBody());
        self::assertSame(['content-type' => 'application/json'], $response->getHeaders());
    }

    public function testHeaderLookupIsCaseInsensitive(): void
    {
        $response = new Response(200, ['ETag' => 'W/"dict-1"']);

        self::assertSame('W/"dict-1"', $response->getHeader('etag'));
        self::assertSame('W/"dict-1"', $response->getHeader('ETAG'));
        self::assertNull($response->getHeader('x-missing'));
    }

    /**
     * @dataProvider provideStatusCodes
     */
    public function testIsSuccessful(int $statusCode, bool $expected): void
    {
        self::assertSame($expected, (new Response($statusCode))->isSuccessful());
    }

    public static function provideStatusCodes(): array
    {
        return [
            [200, true],
            [201, true],
            [204, true],
            [304, false],
            [404, false],
            [500, false],
        ];
    }

    public function testJsonDecodesBody(): void
    {
        $response = new Response(200, [], '{"payload":[{"id":"a"}],"message":null}');

        self::assertSame(['payload' => [['id' => 'a']], 'message' => null], $response->json());
    }

    public function testJsonReturnsEmptyArrayForEmptyBody(): void
    {
        self::assertSame([], (new Response(204))->json());
    }

    public function testJsonRejectsInvalidJson(): void
    {
        $response = new Response(200, [], '<html>oops</html>');

        try {
            $response->json();
            self::fail('Expected UnexpectedResponseException.');
        } catch (UnexpectedResponseException $e) {
            self::assertSame($response, $e->getResponse());
            self::assertStringContainsString('<html>oops</html>', $e->getMessage());
        }
    }

    public function testJsonRejectsScalarJson(): void
    {
        $this->expectException(UnexpectedResponseException::class);

        (new Response(200, [], '"just a string"'))->json();
    }
}
