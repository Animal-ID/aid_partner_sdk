<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Resource;

use AnimalId\PartnerSdk\Http\ApiClient;
use AnimalId\PartnerSdk\Http\Response;

/**
 * Base class for endpoint groups: holds the ApiClient and payload helpers.
 */
abstract class AbstractResource
{
	/** @var ApiClient */
	protected $api;

	public function __construct(ApiClient $api)
	{
		$this->api = $api;
	}

	/**
	 * Extracts the "payload" envelope from a successful response.
	 *
	 * @return array<mixed>
	 */
	protected function payload(Response $response): array
	{
		$decoded = $response->json();
		$payload = $decoded['payload'] ?? [];

		return is_array($payload) ? $payload : [];
	}

	/**
	 * Single-resource endpoints may wrap the object in a one-element array
	 * ({ "payload": [ {...} ] }) — unwrap it; plain objects pass through.
	 *
	 * @param array<mixed> $payload
	 *
	 * @return array<string, mixed>
	 */
	protected function unwrapSingle(array $payload): array
	{
		if (count($payload) === 1 && isset($payload[0]) && is_array($payload[0])) {
			return $payload[0];
		}

		return $payload;
	}

	/**
	 * Maps a list payload through a model factory, skipping non-array entries.
	 *
	 * @param array<mixed> $payload
	 * @param callable(array<string, mixed>): object $factory
	 *
	 * @return list<object>
	 */
	protected function mapList(array $payload, callable $factory): array
	{
		$models = [];
		foreach ($payload as $item) {
			if (is_array($item)) {
				$models[] = $factory($item);
			}
		}

		return $models;
	}
}
