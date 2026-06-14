<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Tests\Unit\Auth;

use AnimalId\PartnerSdk\Auth\RequestSigner;
use PHPUnit\Framework\TestCase;

final class RequestSignerTest extends TestCase
{
    /**
     * Reference vectors computed independently (Python hmac/hashlib)
     * from the formula published in the Partner API docs.
     *
     * @dataProvider provideSignatureVectors
     */
    public function testProducesDocumentedSignature(
        string $privateKey,
        string $method,
        string $pathWithQuery,
        string $body,
        int $timestamp,
        string $expected
    ): void {
        $signer = new RequestSigner($privateKey);

        self::assertSame($expected, $signer->sign($method, $pathWithQuery, $body, $timestamp));
    }

    public static function provideSignatureVectors(): array
    {
        return [
            'GET with query, empty body' => [
                'sk_test_secret',
                'GET',
                '/v1/partner/owners/search?email_or_phone=jane%40example.com',
                '',
                1748592000,
                'cb2e2efbd3ed4ba83da80d1a21bbde77d9178e927a8f651839bc50ec7b2302f3',
            ],
            'POST with JSON body' => [
                'sk_test_secret',
                'POST',
                '/v1/partner/owners',
                '{"email":"jane@example.com"}',
                1748592000,
                '4b5be47e33566334d5cc8f3e01071bbfe0125725e5255ced1d1154d0b33fb9d5',
            ],
            'PATCH, another key' => [
                'another-key',
                'PATCH',
                '/v1/partner/animals/8xK3pQzVnB7rL2qF',
                '{"nickname":"X"}',
                1700000000,
                '906b9de580365010c6adb4bfc90c72c7152e543fbac47600659ca5aceb67e779',
            ],
        ];
    }

    public function testUppercasesTheMethod(): void
    {
        $signer = new RequestSigner('sk_test_secret');

        self::assertSame(
            $signer->sign('POST', '/v1/partner/owners', '{"email":"jane@example.com"}', 1748592000),
            $signer->sign('post', '/v1/partner/owners', '{"email":"jane@example.com"}', 1748592000)
        );
    }

    public function testSignatureChangesWithEveryComponent(): void
    {
        $signer = new RequestSigner('sk_test_secret');
        $base = $signer->sign('GET', '/v1/partner/animals/a', '', 1748592000);

        self::assertNotSame($base, $signer->sign('POST', '/v1/partner/animals/a', '', 1748592000));
        self::assertNotSame($base, $signer->sign('GET', '/v1/partner/animals/b', '', 1748592000));
        self::assertNotSame($base, $signer->sign('GET', '/v1/partner/animals/a', 'x', 1748592000));
        self::assertNotSame($base, $signer->sign('GET', '/v1/partner/animals/a', '', 1748592001));
        self::assertNotSame($base, (new RequestSigner('other'))->sign('GET', '/v1/partner/animals/a', '', 1748592000));
    }
}
