<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Tests\Unit\Http;

use AnimalId\PartnerSdk\Auth\RequestSigner;
use AnimalId\PartnerSdk\Exception\AccessDeniedException;
use AnimalId\PartnerSdk\Exception\ApiException;
use AnimalId\PartnerSdk\Exception\AuthenticationException;
use AnimalId\PartnerSdk\Exception\ConflictException;
use AnimalId\PartnerSdk\Exception\InvalidArgumentException;
use AnimalId\PartnerSdk\Exception\NotFoundException;
use AnimalId\PartnerSdk\Exception\PayloadTooLargeException;
use AnimalId\PartnerSdk\Exception\ValidationException;
use AnimalId\PartnerSdk\Tests\Support\ApiClientFactory;
use AnimalId\PartnerSdk\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class ApiClientTest extends TestCase
{
    /** @var FakeHttpClient */
    private $http;

    protected function setUp(): void
    {
        $this->http = new FakeHttpClient();
    }

    public function testGetSendsSignedRequestWithQueryString(): void
    {
        $this->http->queueJson(200, ['payload' => []]);
        $api = ApiClientFactory::create($this->http);

        $api->get('/v1/partner/owners/search', ['email_or_phone' => 'jane@example.com']);

        $request = $this->http->lastRequest();
        $expectedPath = '/v1/partner/owners/search?email_or_phone=' . urlencode('jane@example.com');

        self::assertSame('GET', $request->getMethod());
        self::assertSame(ApiClientFactory::BASE_URL . $expectedPath, $request->getUrl());
        self::assertSame(ApiClientFactory::APP_ID, $request->getHeader('X-Eternity-App-Id'));
        self::assertSame(ApiClientFactory::PUBLIC_KEY, $request->getHeader('X-Eternity-Public-Key'));
        self::assertSame((string) ApiClientFactory::FROZEN_TIME, $request->getHeader('X-Eternity-Timestamp'));

        // The signature must cover the path WITH the query and an empty body.
        $signer = new RequestSigner(ApiClientFactory::PRIVATE_KEY);
        self::assertSame(
            $signer->sign('GET', $expectedPath, '', ApiClientFactory::FROZEN_TIME),
            $request->getHeader('X-Eternity-Signature')
        );

        self::assertNull($request->getHeader('X-Eternity-Idempotency-Key'), 'GET must not carry an idempotency key');
        self::assertNull($request->getBody());
    }

    public function testGetSkipsNullAndEmptyQueryValues(): void
    {
        $this->http->queueJson(200, ['payload' => []]);
        $api = ApiClientFactory::create($this->http);

        $api->get('/v1/partner/dictionaries', ['include' => null, 'q' => '', 'lang' => 'uk']);

        self::assertSame(
            ApiClientFactory::BASE_URL . '/v1/partner/dictionaries?lang=uk',
            $this->http->lastRequest()->getUrl()
        );
    }

    public function testPostSignsTheExactBodyBytesAndGeneratesIdempotencyKey(): void
    {
        $this->http->queueJson(201, ['payload' => ['user_gid' => 1]]);
        $api = ApiClientFactory::create($this->http);

        $api->post('/v1/partner/owners', ['email' => 'jane@example.com', 'name' => 'Барсік']);

        $request = $this->http->lastRequest();
        $body = $request->getBody();

        self::assertNotNull($body);
        self::assertStringContainsString('Барсік', $body, 'unicode must not be escaped');
        self::assertSame('application/json', $request->getHeader('Content-Type'));

        $signer = new RequestSigner(ApiClientFactory::PRIVATE_KEY);
        self::assertSame(
            $signer->sign('POST', '/v1/partner/owners', $body, ApiClientFactory::FROZEN_TIME),
            $request->getHeader('X-Eternity-Signature')
        );

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            (string) $request->getHeader('X-Eternity-Idempotency-Key')
        );
    }

    public function testCustomIdempotencyKeyIsUsedVerbatim(): void
    {
        $this->http->queueJson(201, ['payload' => []]);
        $api = ApiClientFactory::create($this->http);

        $api->post('/v1/partner/owners', ['email' => 'j@e.com'], 'my-retry-key-1');

        self::assertSame('my-retry-key-1', $this->http->lastRequest()->getHeader('X-Eternity-Idempotency-Key'));
    }

    public function testPatchAndDeleteCarryIdempotencyKeys(): void
    {
        $this->http->queueJson(204, []);
        $this->http->queueJson(204, []);
        $api = ApiClientFactory::create($this->http);

        $api->patch('/v1/partner/animals/a1', ['nickname' => 'X']);
        $api->delete('/v1/partner/animals/a1/photos/5');

        foreach ($this->http->getRequests() as $request) {
            self::assertNotNull($request->getHeader('X-Eternity-Idempotency-Key'), $request->getMethod());
        }
    }

    public function testDeleteSignsEmptyBody(): void
    {
        $this->http->queueJson(204, []);
        $api = ApiClientFactory::create($this->http);

        $api->delete('/v1/partner/animals/a1/photos/5');

        $request = $this->http->lastRequest();
        $signer = new RequestSigner(ApiClientFactory::PRIVATE_KEY);

        self::assertNull($request->getBody());
        self::assertSame(
            $signer->sign('DELETE', '/v1/partner/animals/a1/photos/5', '', ApiClientFactory::FROZEN_TIME),
            $request->getHeader('X-Eternity-Signature')
        );
    }

    public function testMultipartSignsEmptyBodyAndSendsFields(): void
    {
        $this->http->queueJson(201, ['payload' => ['id' => 1]]);
        $api = ApiClientFactory::create($this->http);

        $api->postMultipart('/v1/partner/animals/a1/photos', ['kind' => 'avatar']);

        $request = $this->http->lastRequest();
        $signer = new RequestSigner(ApiClientFactory::PRIVATE_KEY);

        self::assertSame(['kind' => 'avatar'], $request->getMultipart());
        self::assertNull($request->getBody());
        self::assertNull($request->getHeader('Content-Type'), 'cURL must set the multipart boundary itself');
        self::assertSame(
            $signer->sign('POST', '/v1/partner/animals/a1/photos', '', ApiClientFactory::FROZEN_TIME),
            $request->getHeader('X-Eternity-Signature')
        );
    }

    public function testVersionHeaderIsSentWhenConfigured(): void
    {
        $this->http->queueJson(200, ['payload' => []]);
        $this->http->queueJson(200, ['payload' => []]);

        $api = ApiClientFactory::create($this->http, ['api_version' => '2026-05-30']);
        $api->get('/v1/partner/dictionaries');
        self::assertSame('2026-05-30', $this->http->lastRequest()->getHeader('X-Eternity-Animal-ID-Version'));

        // With no explicit version the SDK still sends its default target version.
        $api = ApiClientFactory::create($this->http);
        $api->get('/v1/partner/dictionaries');
        self::assertSame(
            \AnimalId\PartnerSdk\Config::DEFAULT_API_VERSION,
            $this->http->lastRequest()->getHeader('X-Eternity-Animal-ID-Version')
        );
    }

    public function testExtraHeadersArePassedThrough(): void
    {
        $this->http->queueJson(304, []);
        $api = ApiClientFactory::create($this->http);

        $api->get('/v1/partner/dictionaries', [], ['If-None-Match' => 'W/"dict-1"']);

        self::assertSame('W/"dict-1"', $this->http->lastRequest()->getHeader('If-None-Match'));
    }

    /**
     * @dataProvider provideErrorStatuses
     */
    public function testMapsErrorStatusesToTypedExceptions(int $statusCode, string $exceptionClass): void
    {
        $this->http->queueJson($statusCode, ['message' => 'Something went wrong']);
        $api = ApiClientFactory::create($this->http);

        try {
            $api->get('/v1/partner/animals/missing');
            self::fail('Expected ' . $exceptionClass);
        } catch (ApiException $e) {
            self::assertInstanceOf($exceptionClass, $e);
            self::assertSame($statusCode, $e->getStatusCode());
            self::assertSame('Something went wrong', $e->getMessage());
        }
    }

    public static function provideErrorStatuses(): array
    {
        return [
            [401, AuthenticationException::class],
            [403, AccessDeniedException::class],
            [404, NotFoundException::class],
            [409, ConflictException::class],
            [413, PayloadTooLargeException::class],
            [422, ValidationException::class],
            [400, ApiException::class],
            [500, ApiException::class],
        ];
    }

    public function testValidationExceptionExposesErrors(): void
    {
        $errors = ['email' => ['Email is invalid.']];
        $this->http->queueJson(422, ['message' => 'Validation failed', 'errors' => $errors]);
        $api = ApiClientFactory::create($this->http);

        try {
            $api->post('/v1/partner/owners', ['email' => 'bad']);
            self::fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            self::assertSame($errors, $e->getErrors());
            self::assertSame(422, $e->getResponse()->getStatusCode());
        }
    }

    public function testErrorWithoutJsonBodyGetsGenericMessage(): void
    {
        $this->http->queue(new \AnimalId\PartnerSdk\Http\Response(500, [], 'Bad gateway'));
        $api = ApiClientFactory::create($this->http);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Partner API request failed with HTTP status 500.');

        $api->get('/v1/partner/dictionaries');
    }

    public function testRejectsBodyThatCannotBeJsonEncoded(): void
    {
        $api = ApiClientFactory::create($this->http);

        $this->expectException(InvalidArgumentException::class);

        $api->post('/v1/partner/owners', ['bad' => "\xB1\x31"]);
    }
}
