<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk;

use AnimalId\PartnerSdk\Exception\InvalidArgumentException;

/**
 * Immutable SDK configuration: credentials, base URL and transport options.
 */
final class Config
{
    const DEFAULT_BASE_URL = 'https://gw.animal-id.net';
    const DEFAULT_TIMEOUT = 30;
    const DEFAULT_CONNECT_TIMEOUT = 10;

    /**
     * API version this SDK targets, sent as X-Eternity-Animal-ID-Version. From this version
     * owners are attached at registration by `public_id` (not the legacy numeric `user_gid`).
     * Override via the `api_version` option to pin an older contract.
     */
    const DEFAULT_API_VERSION = '2026-07-04';

    /** @var string */
    private $appId;

    /** @var string */
    private $publicKey;

    /** @var string */
    private $privateKey;

    /** @var string */
    private $baseUrl;

    /** @var string Date version (YYYY-MM-DD) sent as X-Eternity-Animal-ID-Version. */
    private $apiVersion;

    /** @var int Total request timeout, seconds. */
    private $timeout;

    /** @var int Connection timeout, seconds. */
    private $connectTimeout;

    /**
     * @param array{api_version?: string, timeout?: int, connect_timeout?: int} $options
     */
    public function __construct(
        string $appId,
        string $publicKey,
        string $privateKey,
        string $baseUrl = self::DEFAULT_BASE_URL,
        array $options = []
    ) {
        if ($appId === '') {
            throw new InvalidArgumentException('appId must not be empty.');
        }
        if ($publicKey === '') {
            throw new InvalidArgumentException('publicKey must not be empty.');
        }
        if ($privateKey === '') {
            throw new InvalidArgumentException('privateKey must not be empty.');
        }
        if (filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException(sprintf('baseUrl "%s" is not a valid URL.', $baseUrl));
        }

        $this->appId = $appId;
        $this->publicKey = $publicKey;
        $this->privateKey = $privateKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiVersion = isset($options['api_version'])
            ? (string) $options['api_version']
            : self::DEFAULT_API_VERSION;
        $this->timeout = isset($options['timeout']) ? (int) $options['timeout'] : self::DEFAULT_TIMEOUT;
        $this->connectTimeout = isset($options['connect_timeout'])
            ? (int) $options['connect_timeout']
            : self::DEFAULT_CONNECT_TIMEOUT;

        if ($this->timeout <= 0 || $this->connectTimeout <= 0) {
            throw new InvalidArgumentException('timeout and connect_timeout must be positive integers.');
        }
    }

    public function getAppId(): string
    {
        return $this->appId;
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function getPrivateKey(): string
    {
        return $this->privateKey;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getApiVersion(): string
    {
        return $this->apiVersion;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function getConnectTimeout(): int
    {
        return $this->connectTimeout;
    }
}
