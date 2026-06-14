<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Tests\Unit;

use AnimalId\PartnerSdk\Config;
use AnimalId\PartnerSdk\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testExposesCredentialsAndDefaults(): void
    {
        $config = new Config('aid_app_x', 'pk_x', 'sk_x');

        self::assertSame('aid_app_x', $config->getAppId());
        self::assertSame('pk_x', $config->getPublicKey());
        self::assertSame('sk_x', $config->getPrivateKey());
        self::assertSame(Config::DEFAULT_BASE_URL, $config->getBaseUrl());
        self::assertNull($config->getApiVersion());
        self::assertSame(Config::DEFAULT_TIMEOUT, $config->getTimeout());
        self::assertSame(Config::DEFAULT_CONNECT_TIMEOUT, $config->getConnectTimeout());
    }

    public function testTrimsTrailingSlashFromBaseUrl(): void
    {
        $config = new Config('aid_app_x', 'pk_x', 'sk_x', 'https://gw.example.test/');

        self::assertSame('https://gw.example.test', $config->getBaseUrl());
    }

    public function testAcceptsOptions(): void
    {
        $config = new Config('aid_app_x', 'pk_x', 'sk_x', 'https://gw.example.test', [
            'api_version' => '2026-05-30',
            'timeout' => 5,
            'connect_timeout' => 2,
        ]);

        self::assertSame('2026-05-30', $config->getApiVersion());
        self::assertSame(5, $config->getTimeout());
        self::assertSame(2, $config->getConnectTimeout());
    }

    /**
     * @dataProvider provideInvalidArguments
     */
    public function testRejectsInvalidArguments(
        string $appId,
        string $publicKey,
        string $privateKey,
        string $baseUrl,
        array $options
    ): void {
        $this->expectException(InvalidArgumentException::class);

        new Config($appId, $publicKey, $privateKey, $baseUrl, $options);
    }

    public static function provideInvalidArguments(): array
    {
        return [
            'empty appId' => ['', 'pk', 'sk', Config::DEFAULT_BASE_URL, []],
            'empty publicKey' => ['app', '', 'sk', Config::DEFAULT_BASE_URL, []],
            'empty privateKey' => ['app', 'pk', '', Config::DEFAULT_BASE_URL, []],
            'invalid baseUrl' => ['app', 'pk', 'sk', 'not-a-url', []],
            'zero timeout' => ['app', 'pk', 'sk', Config::DEFAULT_BASE_URL, ['timeout' => 0]],
            'negative connect_timeout' => ['app', 'pk', 'sk', Config::DEFAULT_BASE_URL, ['connect_timeout' => -1]],
        ];
    }
}
