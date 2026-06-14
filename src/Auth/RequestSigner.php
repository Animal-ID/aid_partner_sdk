<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Auth;

/**
 * Computes the HMAC-SHA256 request signature required by the Partner API.
 *
 * stringToSign = METHOD + "\n" + path[?query] + "\n" + sha256_hex(rawBody) + "\n" + timestamp
 * signature    = hex( hmac_sha256(stringToSign, privateKey) )
 */
final class RequestSigner
{
	/** @var string */
	private $privateKey;

	public function __construct(string $privateKey)
	{
		$this->privateKey = $privateKey;
	}

	/**
	 * @param string $method HTTP verb (GET, POST, ...).
	 * @param string $pathWithQuery Path including the query string when present, exactly as sent.
	 * @param string $body The exact raw body bytes to be sent; '' for GET/DELETE/multipart.
	 * @param int $timestamp Unix seconds.
	 */
	public function sign(string $method, string $pathWithQuery, string $body, int $timestamp): string
	{
		$stringToSign = implode("\n", [
			strtoupper($method),
			$pathWithQuery,
			hash('sha256', $body),
			(string)$timestamp,
		]);

		return hash_hmac('sha256', $stringToSign, $this->privateKey);
	}
}
