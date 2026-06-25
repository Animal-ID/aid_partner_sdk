<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Tests\Unit\Resource;

use AnimalId\PartnerSdk\Resource\AnimalsResource;
use AnimalId\PartnerSdk\Tests\Support\ApiClientFactory;
use AnimalId\PartnerSdk\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * Covers the access-request endpoints, the `owners` expand header, and the abilities/owners
 * mapping on the Animal card.
 */
final class AnimalsAccessAndExpandTest extends TestCase
{
	/** @var FakeHttpClient */
	private $http;

	/** @var AnimalsResource */
	private $animals;

	protected function setUp(): void
	{
		$this->http = new FakeHttpClient();
		$this->animals = new AnimalsResource(ApiClientFactory::create($this->http));
	}

	public function testRequestAccessPostsAndMapsState(): void
	{
		$this->http->queueJson(201, ['payload' => [
			'status'              => 'pending',
			'requested_at'        => '2026-06-23T09:00:00+00:00',
			'expires_at'          => '2026-06-30T09:00:00+00:00',
			'retry_after_seconds' => 604800,
		]]);

		$state = $this->animals->requestAccess('8xK3pQzVnB7rL2qF', 'idem-1');

		$request = $this->http->lastRequest();
		self::assertSame('POST', $request->getMethod());
		self::assertSame(
			ApiClientFactory::BASE_URL . '/v1/partner/animals/8xK3pQzVnB7rL2qF/access-request',
			$request->getUrl()
		);
		self::assertSame('idem-1', $request->getHeader('X-Eternity-Idempotency-Key'));
		self::assertTrue($state->isPending());
		self::assertSame(604800, $state->getRetryAfterSeconds());
	}

	public function testAccessStatusGetsState(): void
	{
		$this->http->queueJson(200, ['payload' => ['status' => 'granted', 'retry_after_seconds' => 0]]);

		$state = $this->animals->accessStatus('8xK3pQzVnB7rL2qF');

		$request = $this->http->lastRequest();
		self::assertSame('GET', $request->getMethod());
		self::assertSame(
			ApiClientFactory::BASE_URL . '/v1/partner/animals/8xK3pQzVnB7rL2qF/access-request',
			$request->getUrl()
		);
		self::assertTrue($state->isGranted());
	}

	public function testGetSendsExpandHeaderAndMapsOwners(): void
	{
		$this->http->queueJson(200, ['payload' => [
			'id'        => '8xK3pQzVnB7rL2qF',
			'nickname'  => 'Барсік',
			'abilities' => ['can_edit' => true],
			'owners'    => [
				['user_gid' => 90231, 'is_main_owner' => true, 'display_hint' => 'Ja*** D.', 'country_id' => '804'],
				['user_gid' => 90232, 'is_main_owner' => false],
			],
		]]);

		$animal = $this->animals->get('8xK3pQzVnB7rL2qF', [AnimalsResource::EXPAND_OWNERS]);

		$request = $this->http->lastRequest();
		self::assertSame('["owners"]', $request->getHeader('X-Eternity-Expand'));

		self::assertTrue($animal->canEdit());
		self::assertSame(['can_edit' => true], $animal->getAbilities());

		$owners = $animal->getOwners();
		self::assertNotNull($owners);
		self::assertCount(2, $owners);
		self::assertSame(90231, $owners[0]->getUserGid());
		self::assertTrue($owners[0]->isMainOwner());
		self::assertSame('804', $owners[0]->getCountryId());
		self::assertFalse($owners[1]->isMainOwner());
	}

	public function testNoExpandHeaderWhenNotRequested(): void
	{
		$this->http->queueJson(200, ['payload' => ['id' => 'a1']]);

		$animal = $this->animals->get('a1');

		self::assertNull($this->http->lastRequest()->getHeader('X-Eternity-Expand'));
		self::assertNull($animal->canEdit());
		self::assertNull($animal->getAbilities());
		self::assertNull($animal->getOwners());
	}

	public function testFindByIdentifierForwardsExpand(): void
	{
		$this->http->queueJson(200, ['payload' => [['id' => 'a1', 'abilities' => ['can_edit' => false]]]]);

		$found = $this->animals->findByIdentifier(AnimalsResource::IDENTIFIER_MICROCHIP, '900263000123456', [AnimalsResource::EXPAND_OWNERS]);

		self::assertSame('["owners"]', $this->http->lastRequest()->getHeader('X-Eternity-Expand'));
		self::assertFalse($found[0]->canEdit());
	}
}
