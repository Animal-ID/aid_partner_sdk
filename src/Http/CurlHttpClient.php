<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Http;

use AnimalId\PartnerSdk\Exception\TransportException;

/**
 * Default transport built on ext-curl. No dependencies beyond PHP itself.
 */
final class CurlHttpClient implements HttpClientInterface
{
	/** @var int */
	private $timeout;

	/** @var int */
	private $connectTimeout;

	public function __construct(int $timeout = 30, int $connectTimeout = 10)
	{
		$this->timeout = $timeout;
		$this->connectTimeout = $connectTimeout;
	}

	public function send(Request $request): Response
	{
		$handle = curl_init();
		if ($handle === false) {
			throw new TransportException('Unable to initialize cURL.');
		}

		$responseHeaders = [];
		$options = [
			CURLOPT_URL            => $request->getUrl(),
			CURLOPT_CUSTOMREQUEST  => $request->getMethod(),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => $this->timeout,
			CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
			CURLOPT_HTTPHEADER     => $this->formatHeaders($request->getHeaders()),
			CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
				$parts = explode(':', $line, 2);
				if (count($parts) === 2) {
					$responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
				}

				return strlen($line);
			},
		];

		if ($request->getMultipart() !== null) {
			$options[CURLOPT_POSTFIELDS] = $request->getMultipart();
		} elseif ($request->getBody() !== null) {
			$options[CURLOPT_POSTFIELDS] = $request->getBody();
		}

		curl_setopt_array($handle, $options);

		$body = curl_exec($handle);
		if ($body === false) {
			$error = curl_error($handle);
			$errno = curl_errno($handle);
			curl_close($handle);

			throw new TransportException(sprintf('cURL error %d: %s', $errno, $error), $errno);
		}

		$statusCode = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
		curl_close($handle);

		return new Response($statusCode, $responseHeaders, (string)$body);
	}

	/**
	 * @param array<string, string> $headers
	 *
	 * @return list<string>
	 */
	private function formatHeaders(array $headers): array
	{
		$formatted = [];
		foreach ($headers as $name => $value) {
			$formatted[] = $name . ': ' . $value;
		}

		return $formatted;
	}
}
