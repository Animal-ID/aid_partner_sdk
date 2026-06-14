<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Model;

/**
 * A single dictionary (species, sex, countries, ...) with its items.
 */
final class Dictionary
{
	/** @var string */
	private $key;

	/** @var list<DictionaryItem> */
	private $items;

	/**
	 * @param list<DictionaryItem> $items
	 */
	private function __construct(string $key, array $items)
	{
		$this->key = $key;
		$this->items = $items;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		$items = [];
		if (isset($data['items']) && is_array($data['items'])) {
			foreach ($data['items'] as $item) {
				if (is_array($item)) {
					$items[] = DictionaryItem::fromArray($item);
				}
			}
		}

		return new self((string)($data['key'] ?? ''), $items);
	}

	public function getKey(): string
	{
		return $this->key;
	}

	/**
	 * @return list<DictionaryItem>
	 */
	public function getItems(): array
	{
		return $this->items;
	}

	/**
	 * @param int|string $code
	 */
	public function findByCode($code): ?DictionaryItem
	{
		foreach ($this->items as $item) {
			// Loose comparison is intentional: codes arrive as int for most
			// dictionaries but as numeric strings for countries.
			if ((string)$item->getCode() === (string)$code) {
				return $item;
			}
		}

		return null;
	}
}
