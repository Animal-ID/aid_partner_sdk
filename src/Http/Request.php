<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Http;

/**
 * Immutable HTTP request description handed to the transport.
 *
 * Exactly one of body / multipart may be set: body carries raw (already signed)
 * JSON bytes, multipart carries form fields (values may be \CURLFile instances).
 */
final class Request
{
	/** @var string */
	private $method;

	/** @var string */
	private $url;

	/** @var array<string, string> */
	private $headers;

	/** @var string|null */
	private $body;

	/** @var array<string, mixed>|null */
	private $multipart;

	/**
	 * @param array<string, string> $headers
	 * @param array<string, mixed>|null $multipart
	 */
	public function __construct(
		string $method,
		string $url,
		array $headers = [],
		?string $body = null,
		?array $multipart = null
	) {
		$this->method = strtoupper($method);
		$this->url = $url;
		$this->headers = $headers;
		$this->body = $body;
		$this->multipart = $multipart;
	}

	public function getMethod(): string
	{
		return $this->method;
	}

	public function getUrl(): string
	{
		return $this->url;
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
		foreach ($this->headers as $key => $value) {
			if (strcasecmp($key, $name) === 0) {
				return $value;
			}
		}

		return null;
	}

	public function getBody(): ?string
	{
		return $this->body;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function getMultipart(): ?array
	{
		return $this->multipart;
	}
}
