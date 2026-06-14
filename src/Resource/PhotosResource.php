<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Resource;

use AnimalId\PartnerSdk\Exception\InvalidArgumentException;
use AnimalId\PartnerSdk\Model\Photo;

/**
 * /v1/partner/animals/{id}/photos — upload and delete animal photos.
 */
final class PhotosResource extends AbstractResource
{
	const ANIMALS_PATH = '/v1/partner/animals';

	const KIND_AVATAR = 'avatar';
	const KIND_GALLERY = 'gallery';
	const KIND_NOSE_PRINT = 'nose_print';

	/**
	 * Uploads a photo (multipart/form-data; max 8 MB per photo).
	 *
	 * @param string $filePath Local path to the image file.
	 * @param string|null $kind One of the KIND_* constants; avatar sets the main photo.
	 * Defaults to gallery server-side.
	 */
	public function upload(
		string $animalId,
		string $filePath,
		?string $kind = null,
		?string $idempotencyKey = null
	): Photo {
		if (!is_file($filePath) || !is_readable($filePath)) {
			throw new InvalidArgumentException(sprintf('File "%s" does not exist or is not readable.', $filePath));
		}
		if ($kind !== null
			&& !in_array($kind, [self::KIND_AVATAR, self::KIND_GALLERY, self::KIND_NOSE_PRINT], true)
		) {
			throw new InvalidArgumentException(sprintf(
				'Unknown photo kind "%s"; expected "%s", "%s" or "%s".',
				$kind,
				self::KIND_AVATAR,
				self::KIND_GALLERY,
				self::KIND_NOSE_PRINT
			));
		}

		$multipart = ['file' => new \CURLFile($filePath)];
		if ($kind !== null) {
			$multipart['kind'] = $kind;
		}

		$response = $this->api->postMultipart(
			self::ANIMALS_PATH . '/' . rawurlencode($animalId) . '/photos',
			$multipart,
			$idempotencyKey
		);

		return Photo::fromArray($this->unwrapSingle($this->payload($response)));
	}

	/**
	 * Soft-deletes a photo.
	 */
	public function delete(string $animalId, int $photoId, ?string $idempotencyKey = null): void
	{
		$this->api->delete(
			self::ANIMALS_PATH . '/' . rawurlencode($animalId) . '/photos/' . $photoId,
			$idempotencyKey
		);
	}
}
