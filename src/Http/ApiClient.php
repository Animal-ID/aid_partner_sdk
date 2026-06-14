<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Http;

use AnimalId\PartnerSdk\Auth\IdempotencyKeyGenerator;
use AnimalId\PartnerSdk\Auth\RequestSigner;
use AnimalId\PartnerSdk\Config;
use AnimalId\PartnerSdk\Exception\ApiException;
use AnimalId\PartnerSdk\Exception\InvalidArgumentException;

/**
 * Builds signed Partner API requests and turns 4xx/5xx answers into typed exceptions.
 *
 * Resource classes only describe endpoints; everything common (HMAC signature,
 * X-Eternity-* headers, idempotency keys, error mapping) lives here.
 */
final class ApiClient
{
	/** @var Config */
	private $config;

	/** @var HttpClientInterface */
	private $httpClient;

	/** @var RequestSigner */
	private $signer;

	/** @var IdempotencyKeyGenerator */
	private $idempotencyKeys;

	/** @var callable():int Returns current Unix time; injectable for tests. */
	private $clock;

	public function __construct(
		Config $config,
		HttpClientInterface $httpClient,
		RequestSigner $signer,
		IdempotencyKeyGenerator $idempotencyKeys,
		?callable $clock = null
	) {
		$this->config = $config;
		$this->httpClient = $httpClient;
		$this->signer = $signer;
		$this->idempotencyKeys = $idempotencyKeys;
		$this->clock = $clock !== null ? $clock : static function (): int {
			return time();
		};
	}

	/**
	 * @param array<string, mixed> $query
	 * @param array<string, string> $headers Extra headers (e.g. If-None-Match).
	 */
	public function get(string $path, array $query = [], array $headers = []): Response
	{
		return $this->send('GET', $path, $query, null, null, null, $headers);
	}

	/**
	 * @param array<mixed> $body JSON-serializable body (object or list).
	 */
	public function post(string $path, array $body, ?string $idempotencyKey = null): Response
	{
		return $this->send('POST', $path, [], $body, null, $idempotencyKey);
	}

	/**
	 * @param array<mixed> $body
	 */
	public function patch(string $path, array $body, ?string $idempotencyKey = null): Response
	{
		return $this->send('PATCH', $path, [], $body, null, $idempotencyKey);
	}

	public function delete(string $path, ?string $idempotencyKey = null): Response
	{
		return $this->send('DELETE', $path, [], null, null, $idempotencyKey);
	}

	/**
	 * Multipart upload. Per the API contract the signature covers an EMPTY body.
	 *
	 * @param array<string, mixed> $multipart Form fields; values may be \CURLFile.
	 */
	public function postMultipart(string $path, array $multipart, ?string $idempotencyKey = null): Response
	{
		return $this->send('POST', $path, [], null, $multipart, $idempotencyKey);
	}

	/**
	 * @param array<string, mixed> $query
	 * @param array<mixed>|null $jsonBody
	 * @param array<string, mixed>|null $multipart
	 * @param array<string, string> $extraHeaders
	 */
	private function send(
		string $method,
		string $path,
		array $query,
		?array $jsonBody,
		?array $multipart,
		?string $idempotencyKey,
		array $extraHeaders = []
	): Response {
		$pathWithQuery = $this->buildPath($path, $query);

		$bodyString = null;
		$signedBody = '';
		if ($jsonBody !== null) {
			$bodyString = $this->encodeJson($jsonBody);
			$signedBody = $bodyString;
		}

		$timestamp = (int)call_user_func($this->clock);

		$headers = [
			'Accept'                => 'application/json',
			'X-Eternity-App-Id'     => $this->config->getAppId(),
			'X-Eternity-Public-Key' => $this->config->getPublicKey(),
			'X-Eternity-Timestamp'  => (string)$timestamp,
			'X-Eternity-Signature'  => $this->signer->sign($method, $pathWithQuery, $signedBody, $timestamp),
		];

		if ($bodyString !== null) {
			$headers['Content-Type'] = 'application/json';
		}
		if ($this->isWriteMethod($method)) {
			$headers['X-Eternity-Idempotency-Key'] = $idempotencyKey !== null && $idempotencyKey !== ''
				? $idempotencyKey
				: $this->idempotencyKeys->generate();
		}
		if ($this->config->getApiVersion() !== null) {
			$headers['X-Eternity-Animal-ID-Version'] = $this->config->getApiVersion();
		}

		$headers = array_merge($headers, $extraHeaders);

		$response = $this->httpClient->send(new Request(
			$method,
			$this->config->getBaseUrl() . $pathWithQuery,
			$headers,
			$bodyString,
			$multipart
		));

		if ($response->getStatusCode() >= 400) {
			throw ApiException::create($response);
		}

		return $response;
	}

	/**
	 * @param array<string, mixed> $query
	 */
	private function buildPath(string $path, array $query): string
	{
		$query = array_filter($query, static function ($value): bool {
			return $value !== null && $value !== '';
		});

		if ($query === []) {
			return $path;
		}

		return $path . '?' . http_build_query($query);
	}

	/**
	 * @param array<mixed> $body
	 */
	private function encodeJson(array $body): string
	{
		$json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($json === false) {
			throw new InvalidArgumentException('Request body cannot be encoded to JSON: ' . json_last_error_msg());
		}

		return $json;
	}

	private function isWriteMethod(string $method): bool
	{
		return in_array($method, ['POST', 'PATCH', 'PUT', 'DELETE'], true);
	}
}
