<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Exception;

use AnimalId\PartnerSdk\Http\Response;

/**
 * Base exception for any HTTP 4xx/5xx answer from the Partner API.
 * Factory method create() maps well-known statuses to dedicated subclasses.
 */
class ApiException extends \RuntimeException implements PartnerSdkException
{
	/** @var Response */
	private $response;

	public function __construct(string $message, Response $response)
	{
		parent::__construct($message, $response->getStatusCode());
		$this->response = $response;
	}

	public static function create(Response $response): self
	{
		$message = self::extractMessage($response);

		switch ($response->getStatusCode()) {
			case 401:
				return new AuthenticationException($message, $response);
			case 403:
				return new AccessDeniedException($message, $response);
			case 404:
				return new NotFoundException($message, $response);
			case 409:
				return new ConflictException($message, $response);
			case 413:
				return new PayloadTooLargeException($message, $response);
			case 422:
				return new ValidationException($message, $response, self::extractErrors($response));
		}

		return new self($message, $response);
	}

	public function getResponse(): Response
	{
		return $this->response;
	}

	public function getStatusCode(): int
	{
		return $this->response->getStatusCode();
	}

	private static function extractMessage(Response $response): string
	{
		$decoded = json_decode($response->getBody(), true);
		if (is_array($decoded)) {
			foreach (['message', 'error'] as $key) {
				if (isset($decoded[$key]) && is_string($decoded[$key]) && $decoded[$key] !== '') {
					return $decoded[$key];
				}
			}
		}

		return sprintf('Partner API request failed with HTTP status %d.', $response->getStatusCode());
	}

	/**
	 * @return array<mixed>
	 */
	private static function extractErrors(Response $response): array
	{
		$decoded = json_decode($response->getBody(), true);
		if (is_array($decoded) && isset($decoded['errors']) && is_array($decoded['errors'])) {
			return $decoded['errors'];
		}

		return [];
	}
}
