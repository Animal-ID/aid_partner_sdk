<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Tests\Unit\Resource;

use AnimalId\PartnerSdk\Exception\InvalidArgumentException;
use AnimalId\PartnerSdk\Model\ConsentRequest;
use AnimalId\PartnerSdk\Resource\ClinicsResource;
use AnimalId\PartnerSdk\Resource\ConsentsResource;
use AnimalId\PartnerSdk\Resource\DoctorsResource;
use AnimalId\PartnerSdk\Tests\Support\ApiClientFactory;
use AnimalId\PartnerSdk\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * The provisioning plane: clinics, doctors, and the permissions in between.
 */
final class PlatformResourcesTest extends TestCase
{
    /** @var FakeHttpClient */
    private $http;

    protected function setUp(): void
    {
        $this->http = new FakeHttpClient();
    }

    private function clinics(): ClinicsResource
    {
        return new ClinicsResource(ApiClientFactory::create($this->http));
    }

    private function doctors(): DoctorsResource
    {
        return new DoctorsResource(ApiClientFactory::create($this->http));
    }

    private function consents(): ConsentsResource
    {
        return new ConsentsResource(ApiClientFactory::create($this->http));
    }

    public function testSearchReturnsClinicsAndFlagsYourOwn(): void
    {
        $this->http->queueJson(200, [
            'payload' => [
                ['public_id' => 'TGo1Gwe2ppRkUFO1', 'org_name' => 'Лапа', 'full_address' => 'Київ', 'status' => 3, 'linked' => false],
                ['public_id' => 'yAvgJrSYehJo9JXh', 'org_name' => 'Мурка', 'status' => 2, 'linked' => true],
            ],
        ]);

        $found = $this->clinics()->search('лап', 10);

        $request = $this->http->lastRequest();
        self::assertSame('GET', $request->getMethod());
        self::assertStringContainsString('/v1/platform/organizations', $request->getUrl());
        self::assertStringContainsString('limit=10', $request->getUrl());

        self::assertCount(2, $found);
        // `org_name` on search and `name` on provisioning are the same thing; the model hides that.
        self::assertSame('Лапа', $found[0]->getName());
        // Whether a clinic is yours decides if you need the director's permission to seat anyone.
        self::assertFalse($found[0]->isLinked());
        self::assertTrue($found[1]->isLinked());
    }

    public function testSearchRefusesAQueryTooShortToMeanAnything(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->clinics()->search('л');
    }

    public function testProvisionRequiresADirector(): void
    {
        // A clinic cannot exist without one, so catching it here saves a round trip.
        $this->expectException(InvalidArgumentException::class);

        $this->clinics()->provision(['external_org_id' => 'crm-1', 'name' => 'Лапа']);
    }

    public function testProvisionReportsWhetherItCreatedOrResolved(): void
    {
        // The same external_org_id must resolve to the clinic you already have — that is what makes
        // a retried signup safe.
        $this->http->queueJson(200, [
            'payload' => ['public_id' => 'yAvgJrSYehJo9JXh', 'name' => 'Лапа', 'status' => 2, 'created' => false],
        ]);

        $clinic = $this->clinics()->provision([
            'external_org_id'    => 'crm-clinic-118',
            'name'               => 'Лапа',
            'director_public_id' => 'V1StGXR8Z5jd',
        ]);

        self::assertSame('yAvgJrSYehJo9JXh', $clinic->getPublicId());
        self::assertFalse($clinic->wasCreated());
    }

    public function testSeatingADoctorReturnsCredentialsOnce(): void
    {
        $this->http->queueJson(201, [
            'payload' => [
                'public_id'   => 'LSG9w6B2rwAiPoJD',
                'app_id'      => '4833d2e5-1a97-4572-ac5d-a03c7614e0d7',
                'public_key'  => '94af0494',
                'private_key' => 'b208c6e0',
                'created'     => true,
            ],
        ]);

        $doctor = $this->doctors()->seat('yAvgJrSYehJo9JXh', [
            'email'   => 'doctor@example.com',
            'consent' => ['account_creation' => true],
        ]);

        $request = $this->http->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame(
            ApiClientFactory::BASE_URL . '/v1/platform/organizations/yAvgJrSYehJo9JXh/members',
            $request->getUrl()
        );

        self::assertSame('b208c6e0', $doctor->getPrivateKey());
        self::assertTrue($doctor->wasCreated());
    }

    public function testSeatingRefusesWithoutTheDoctorsAgreement(): void
    {
        // The credentials act as this person; sending the call without their consent would only
        // waste a 422.
        $this->expectException(InvalidArgumentException::class);

        $this->doctors()->seat('yAvgJrSYehJo9JXh', ['email' => 'doctor@example.com']);
    }

    public function testSeatingRefusesWithoutAnyWayToIdentifyTheDoctor(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->doctors()->seat('yAvgJrSYehJo9JXh', ['consent' => ['account_creation' => true]]);
    }

    public function testCredentialsAreCollectedFromTheDoctorScopedPath(): void
    {
        $this->http->queueJson(201, [
            'payload' => [
                'public_id'   => 'LSG9w6B2rwAiPoJD',
                'app_id'      => 'dcd4895e-550e-424a-9c5d-5206cbce2956',
                'public_key'  => '11fff5d1',
                'private_key' => '00604b9b',
                'created'     => false,
            ],
        ]);

        $credentials = $this->doctors()->credentials('yAvgJrSYehJo9JXh', 'LSG9w6B2rwAiPoJD');

        self::assertSame(
            ApiClientFactory::BASE_URL
            . '/v1/platform/organizations/yAvgJrSYehJo9JXh/members/LSG9w6B2rwAiPoJD/credentials',
            $this->http->lastRequest()->getUrl()
        );
        // Nobody was created — this doctor already existed and agreed to the handover.
        self::assertFalse($credentials->wasCreated());
    }

    public function testCredentialsBuildTheDataPlaneConfigDirectly(): void
    {
        // So the private key travels from the response into the client that uses it, without a
        // detour through code that might log it.
        $this->http->queueJson(201, [
            'payload' => [
                'public_id'   => 'LSG9w6B2rwAiPoJD',
                'app_id'      => 'dcd4895e',
                'public_key'  => '11fff5d1',
                'private_key' => '00604b9b',
                'created'     => false,
            ],
        ]);

        $config = $this->doctors()->credentials('c', 'd')->toConfig('https://gw.example.test');

        self::assertSame('dcd4895e', $config->getAppId());
        self::assertSame('00604b9b', $config->getPrivateKey());
    }

    public function testAKeyHandoverAsksTheDoctorAndNamesNoClinic(): void
    {
        // Only the doctor can allow it — the key signs as them, so no clinic is involved.
        $this->http->queueJson(201, [
            'payload' => ['public_id' => '5G5rBYl0hQvpJuCZ', 'kind' => 'key_handover', 'status' => 'pending', 'expires_at' => 1788973565, 'decided_at' => null],
        ]);

        $consent = $this->consents()->requestKeyHandover('LSG9w6B2rwAiPoJD');

        $body = json_decode((string) $this->http->lastRequest()->getBody(), true);
        self::assertSame('key_handover', $body['kind']);
        self::assertArrayNotHasKey('clinic_public_id', $body);
        self::assertTrue($consent->isPending());
    }

    public function testAClinicMembershipRequestNeedsTheClinic(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->consents()->request(ConsentRequest::KIND_CLINIC_MEMBERSHIP, 'LSG9w6B2rwAiPoJD');
    }

    public function testAnUnknownKindIsRefusedBeforeTheCall(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->consents()->request('whatever', 'LSG9w6B2rwAiPoJD');
    }

    public function testAnApprovalIsOnlyUsableWhileItLasts(): void
    {
        // Status alone is not enough: an approval stops working when its window closes, so acting
        // on a year-old yes must not be possible.
        $this->http->queueJson(200, [
            'payload' => ['public_id' => 'x', 'kind' => 'key_handover', 'status' => 'approved', 'expires_at' => time() - 1],
        ]);

        self::assertFalse($this->consents()->status('x')->isUsable());
    }

    public function testAnExpiredRequestIsNotARefusal(): void
    {
        // Nobody looked. You may ask again — which a partner deciding what to do next has to know.
        $this->http->queueJson(200, [
            'payload' => ['public_id' => 'x', 'kind' => 'key_handover', 'status' => 'expired', 'expires_at' => time() - 1],
        ]);

        $consent = $this->consents()->status('x');

        self::assertTrue($consent->isFinished());
        self::assertSame(ConsentRequest::STATUS_EXPIRED, $consent->getStatus());
    }
}
