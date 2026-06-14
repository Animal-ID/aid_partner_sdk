<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Model;

/**
 * One entry of a dictionary (a species, a country, a language, ...).
 */
final class DictionaryItem
{
	/** @var int|string Stable id used as the value in write endpoints. */
	private $code;

	/** @var array<string, string> Map locale => localized name. */
	private $names;

	/** @var string|null Countries only: ISO 3166-1 alpha-2. */
	private $alpha2;

	/** @var string|null Countries only: ISO 3166-1 alpha-3. */
	private $alpha3;

	/** @var string|null Languages only: the language's own name (endonym). */
	private $native;

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
		$item = new self();
		$item->code = $data['code'] ?? '';
		$item->names = isset($data['names']) && is_array($data['names']) ? $data['names'] : [];
		$item->alpha2 = isset($data['alpha2']) ? (string)$data['alpha2'] : null;
		$item->alpha3 = isset($data['alpha3']) ? (string)$data['alpha3'] : null;
		$item->native = isset($data['native']) ? (string)$data['native'] : null;
		$item->raw = $data;

		return $item;
	}

	/**
	 * @return int|string
	 */
	public function getCode()
	{
		return $this->code;
	}

	/**
	 * @return array<string, string>
	 */
	public function getNames(): array
	{
		return $this->names;
	}

	/**
	 * Localized name with a fallback to English, then to any available locale.
	 */
	public function getName(string $locale = 'en'): ?string
	{
		if (isset($this->names[$locale])) {
			return $this->names[$locale];
		}
		if (isset($this->names['en'])) {
			return $this->names['en'];
		}

		$first = reset($this->names);

		return $first === false ? null : $first;
	}

	public function getAlpha2(): ?string
	{
		return $this->alpha2;
	}

	public function getAlpha3(): ?string
	{
		return $this->alpha3;
	}

	public function getNative(): ?string
	{
		return $this->native;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return $this->raw;
	}
}
