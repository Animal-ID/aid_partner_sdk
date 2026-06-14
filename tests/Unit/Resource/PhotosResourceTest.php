<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Tests\Unit\Resource;

use AnimalId\PartnerSdk\Exception\AccessDeniedException;
use AnimalId\PartnerSdk\Exception\InvalidArgumentException;
use AnimalId\PartnerSdk\Resource\PhotosResource;
use AnimalId\PartnerSdk\Tests\Support\ApiClientFactory;
use AnimalId\PartnerSdk\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class PhotosResourceTest extends TestCase
{
    /** @var FakeHttpClient */
    private $http;

    /** @var PhotosResource */
    private $photos;

    /** @var string */
    private $tmpFile;

    protected function setUp(): void
    {
        $this->http = new FakeHttpClient();
        $this->photos = new PhotosResource(ApiClientFactory::create($this->http));
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'sdk_photo_');
        file_put_contents($this->tmpFile, 'fake-image-bytes');
    }

    protected function tearDown(): void
    {
        if (is_file($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testUploadSendsMultipartWithFileAndKind(): void
    {
        $this->http->queueJson(201, ['payload' => ['id' => 33015]]);

        $photo = $this->photos->upload('8xK3pQzVnB7rL2qF', $this->tmpFile, PhotosResource::KIND_AVATAR);

        $request = $this->http->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame(
            ApiClientFactory::BASE_URL . '/v1/partner/animals/8xK3pQzVnB7rL2qF/photos',
            $request->getUrl()
        );

        $multipart = $request->getMultipart();
        self::assertNotNull($multipart);
        self::assertInstanceOf(\CURLFile::class, $multipart['file']);
        self::assertSame($this->tmpFile, $multipart['file']->getFilename());
        self::assertSame('avatar', $multipart['kind']);
        self::assertNotNull($request->getHeader('X-Eternity-Idempotency-Key'));

        self::assertSame(33015, $photo->getId());
    }

    public function testUploadOmitsKindWhenNotGiven(): void
    {
        $this->http->queueJson(201, ['payload' => ['id' => 1]]);

        $this->photos->upload('a1', $this->tmpFile);

        self::assertArrayNotHasKey('kind', $this->http->lastRequest()->getMultipart());
    }

    public function testUploadRejectsMissingFile(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->photos->upload('a1', '/nonexistent/photo.jpg');
    }

    public function testUploadRejectsUnknownKind(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->photos->upload('a1', $this->tmpFile, 'panorama');
    }

    public function testUploadPropagatesAccessDenied(): void
    {
        $this->http->queueJson(403, ['message' => 'No relation to this animal']);

        $this->expectException(AccessDeniedException::class);

        $this->photos->upload('a1', $this->tmpFile);
    }

    public function testDeleteSendsDeleteWithIdempotencyKey(): void
    {
        $this->http->queueJson(204, []);

        $this->photos->delete('8xK3pQzVnB7rL2qF', 33015, 'del-key');

        $request = $this->http->lastRequest();
        self::assertSame('DELETE', $request->getMethod());
        self::assertSame(
            ApiClientFactory::BASE_URL . '/v1/partner/animals/8xK3pQzVnB7rL2qF/photos/33015',
            $request->getUrl()
        );
        self::assertSame('del-key', $request->getHeader('X-Eternity-Idempotency-Key'));
        self::assertNull($request->getBody());
    }
}
