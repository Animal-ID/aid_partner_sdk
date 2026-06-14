<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Http;

use AnimalId\PartnerSdk\Exception\UnexpectedResponseException;

/**
 * Immutable HTTP response returned by the transport.
 */
final class Response
{
	/** @var int */
	private $statusCode;

	/** @var array<string, string> Header names normalized to lower case. */
	private $headers;

	/** @var string */
	private $body;

	/**
	 * @param array<string, string> $headers
	 */
	public function __construct(int $statusCode, array $headers = [], string $body = '')
	{
		$this->statusCode = $statusCode;
		$this->headers = [];
		foreach ($headers as $name => $value) {
			$this->headers[strtolower((string)$name)] = (string)$value;
		}
		$this->body = $body;
	}

	public function getStatusCode(): int
	{
		return $this->statusCode;
	}

	/**
	 * @return array<string, string>
	 */
	public function getHeaders(): array
	{
		return $this->headers;
	}

	public function getHeader(string $name): ?string
	{
		$name = strtolower($name);

		return isset($this->headers[$name]) ? $this->headers[$name] : null;
	}

	public function getBody(): string
	{
		return $this->body;
	}

	public function isSuccessful(): bool
	{
		return $this->statusCode >= 200 && $this->statusCode < 300;
	}

	/**
	 * Decodes the JSON body into an associative array. An empty body decodes to [].
	 *
	 * @return array<mixed>
	 *
	 * @throws UnexpectedResponseException When the body is not valid JSON.
	 */
	public function json(): array
	{
		if ($this->body === '') {
			return [];
		}

		$decoded = json_decode($this->body, true);
		if (!is_array($decoded)) {
			throw new UnexpectedResponseException(
				sprintf('Expected a JSON object/array response, got: %s', substr($this->body, 0, 200)),
				$this
			);
		}

		return $decoded;
	}
}
