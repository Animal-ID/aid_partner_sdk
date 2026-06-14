<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Model;

/**
 * Result of GET /dictionaries: the dictionaries plus caching metadata.
 *
 * When the request was made with a known ETag and the server answered
 * 304 Not Modified, isNotModified() returns true and the set is empty —
 * keep using your cached copy.
 */
final class DictionarySet
{
	/** @var array<string, Dictionary> Keyed by dictionary key. */
	private $dictionaries = [];

	/** @var string|null */
	private $etag;

	/** @var string|null */
	private $generatedAt;

	/** @var list<string> Active locales present in this build. */
	private $languages = [];

	/** @var bool */
	private $notModified = false;

	private function __construct()
	{
	}

	/**
	 * @param array<string, mixed> $decoded Full decoded response body.
	 */
	public static function fromResponse(array $decoded): self
	{
		$set = new self();

		if (isset($decoded['payload']) && is_array($decoded['payload'])) {
			foreach ($decoded['payload'] as $dictionaryData) {
				if (is_array($dictionaryData)) {
					$dictionary = Dictionary::fromArray($dictionaryData);
					$set->dictionaries[$dictionary->getKey()] = $dictionary;
				}
			}
		}

		$metadata = isset($decoded['metadata']) && is_array($decoded['metadata']) ? $decoded['metadata'] : [];
		$set->etag = isset($metadata['etag']) ? (string)$metadata['etag'] : null;
		$set->generatedAt = isset($metadata['generated_at']) ? (string)$metadata['generated_at'] : null;
		$set->languages = isset($metadata['languages']) && is_array($metadata['languages'])
			? array_values($metadata['languages'])
			: [];

		return $set;
	}

	public static function notModified(?string $etag): self
	{
		$set = new self();
		$set->etag = $etag;
		$set->notModified = true;

		return $set;
	}

	public function isNotModified(): bool
	{
		return $this->notModified;
	}

	/**
	 * @return array<string, Dictionary>
	 */
	public function all(): array
	{
		return $this->dictionaries;
	}

	public function get(string $key): ?Dictionary
	{
		return isset($this->dictionaries[$key]) ? $this->dictionaries[$key] : null;
	}

	public function getEtag(): ?string
	{
		return $this->etag;
	}

	public function getGeneratedAt(): ?string
	{
		return $this->generatedAt;
	}

	/**
	 * @return list<string>
	 */
	public function getLanguages(): array
	{
		return $this->languages;
	}
}
