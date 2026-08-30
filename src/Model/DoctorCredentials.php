<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Model;

/**
 * A doctor and the key pair that signs the data plane as them.
 *
 * **The private key is shown in this one response and never again.** Store it before you discard
 * the object; there is no endpoint that returns it a second time, and asking for credentials again
 * answers 409 while the existing key is active.
 *
 * The pair belongs to a (doctor, clinic) pair, so the same doctor working in two clinics has two.
 */
final class DoctorCredentials
{
	/** @var string The doctor's stable public identifier — use it wherever a person is named. */
	private $publicId;

	/** @var string App-Id this doctor signs data-plane calls with (X-Eternity-App-Id). */
	private $appId;

	/** @var string Public half of the key pair. */
	private $publicKey;

	/** @var string Private half. Never returned again. */
	private $privateKey;

	/**
	 * @var bool True when this call created the account, false when the doctor already had one and
	 *           was matched by email/phone.
	 */
	private $created;

	/** @var array<string, mixed> Raw payload for forward compatibility. */
	private $raw;

	private function __construct()
	{
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		$credentials = new self();
		$credentials->publicId = (string)($data['public_id'] ?? '');
		$credentials->appId = (string)($data['app_id'] ?? '');
		$credentials->publicKey = (string)($data['public_key'] ?? '');
		$credentials->privateKey = (string)($data['private_key'] ?? '');
		$credentials->created = (bool)($data['created'] ?? false);
		$credentials->raw = $data;

		return $credentials;
	}

	public function getPublicId(): string
	{
		return $this->publicId;
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

	public function wasCreated(): bool
	{
		return $this->created;
	}

	/**
	 * Builds the data-plane configuration for this doctor, ready to hand to a
	 * {@see \AnimalId\PartnerSdk\PartnerClient}.
	 *
	 * Exists so the private key travels from the response straight into the client that uses it,
	 * without a round trip through your own code that might log it on the way.
	 *
	 * @param array{api_version?: string, timeout?: int, connect_timeout?: int} $options
	 */
	public function toConfig(
		string $baseUrl = \AnimalId\PartnerSdk\Config::DEFAULT_BASE_URL,
		array $options = []
	): \AnimalId\PartnerSdk\Config {
		return new \AnimalId\PartnerSdk\Config(
			$this->appId,
			$this->publicKey,
			$this->privateKey,
			$baseUrl,
			$options
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return $this->raw;
	}
}
