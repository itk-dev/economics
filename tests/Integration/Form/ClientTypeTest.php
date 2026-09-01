<?php

namespace App\Tests\Integration\Form;

use App\Entity\Client;
use App\Entity\Project;
use App\Entity\Version;
use App\Enum\ClientTypeEnum;
use App\Form\ClientType;
use App\Service\ProjectBillingService;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;

class ClientTypeTest extends AbstractFormTestCase
{
    private const OPTIONS = ['standard_price' => 750.0];

    public function testFormExposesExpectedFields(): void
    {
        $this->assertHasFields(
            ClientType::class,
            ['name', 'contact', 'standardPrice', 'type', 'customerKey', 'psp', 'ean', 'versionName'],
            null,
            self::OPTIONS
        );
    }

    public function testDataClassIsClient(): void
    {
        $form = $this->createForm(ClientType::class, null, self::OPTIONS);

        $this->assertSame(Client::class, $form->getConfig()->getOption('data_class'));
    }

    public function testStandardPriceOptionIsRequired(): void
    {
        $this->expectException(MissingOptionsException::class);

        $this->createForm(ClientType::class);
    }

    public function testStandardPriceOptionMustBeFloat(): void
    {
        $this->expectException(InvalidOptionsException::class);

        $this->createForm(ClientType::class, null, ['standard_price' => 'not-a-float']);
    }

    public function testTypeChoicesExcludeLegacyExternalByDefault(): void
    {
        $form = $this->createForm(ClientType::class, new Client(), self::OPTIONS);

        $this->assertSame(
            [ClientTypeEnum::INTERNAL, ClientTypeEnum::EXTERNAL_WITH_MOMS, ClientTypeEnum::EXTERNAL_WITHOUT_MOMS],
            $form->get('type')->getConfig()->getOption('choices')
        );
    }

    public function testTypeChoicesKeepLegacyExternalForClientsStillUsingIt(): void
    {
        $client = new Client();
        $client->setType(ClientTypeEnum::EXTERNAL);

        $form = $this->createForm(ClientType::class, $client, self::OPTIONS);

        $this->assertSame(
            [
                ClientTypeEnum::INTERNAL,
                ClientTypeEnum::EXTERNAL_WITH_MOMS,
                ClientTypeEnum::EXTERNAL_WITHOUT_MOMS,
                ClientTypeEnum::EXTERNAL,
            ],
            $form->get('type')->getConfig()->getOption('choices')
        );
    }

    /**
     * @dataProvider typeLabelProvider
     */
    public function testTypeChoiceLabels(ClientTypeEnum $type, string $expected): void
    {
        $choiceLabel = $this->createForm(ClientType::class, new Client(), self::OPTIONS)
            ->get('type')->getConfig()->getOption('choice_label');

        $this->assertSame($expected, $choiceLabel($type));
    }

    /**
     * @return array<string, array{ClientTypeEnum, string}>
     */
    public static function typeLabelProvider(): array
    {
        return [
            'internal' => [ClientTypeEnum::INTERNAL, 'client_type_enum.internal'],
            'legacy external' => [ClientTypeEnum::EXTERNAL, 'client_type_enum.external'],
            'with moms' => [ClientTypeEnum::EXTERNAL_WITH_MOMS, 'client_type_enum.external_with_moms'],
            'without moms' => [ClientTypeEnum::EXTERNAL_WITHOUT_MOMS, 'client_type_enum.external_without_moms'],
        ];
    }

    public function testVersionNameChoicesOnlyContainUnassignedProjectBillingVersions(): void
    {
        $unassigned = $this->persistVersion(ProjectBillingService::PROJECT_BILLING_VERSION_PREFIX.'unassigned');

        $choices = $this->createForm(ClientType::class, new Client(), self::OPTIONS)
            ->get('versionName')->getConfig()->getOption('choices');

        $this->assertContains($unassigned, $choices);

        foreach ($choices as $name) {
            $this->assertStringStartsWith(ProjectBillingService::PROJECT_BILLING_VERSION_PREFIX, $name);
        }

        $assigned = array_filter(array_map(
            fn (Client $client) => $client->getVersionName(),
            $this->entityManager->getRepository(Client::class)->findAll()
        ));

        $this->assertSame([], array_intersect($assigned, (array) $choices));
    }

    public function testVersionNameChoicesExcludeVersionsAlreadyAssignedToAnotherClient(): void
    {
        $versionName = ProjectBillingService::PROJECT_BILLING_VERSION_PREFIX.'taken';
        $this->persistVersion($versionName);

        $owner = new Client();
        $owner->setName('Owning client');
        $owner->setVersionName($versionName);
        $this->entityManager->persist($owner);
        $this->entityManager->flush();

        $choices = $this->createForm(ClientType::class, new Client(), self::OPTIONS)
            ->get('versionName')->getConfig()->getOption('choices');

        $this->assertNotContains($versionName, $choices);
    }

    private function persistVersion(string $name): string
    {
        $version = new Version();
        $version->setName($name);
        $version->setProjectTrackerId($name);
        $version->setProject($this->findOne(Project::class));
        $this->entityManager->persist($version);
        $this->entityManager->flush();

        return $name;
    }

    public function testVersionNameChoicesAlwaysIncludeTheClientsOwnVersion(): void
    {
        $assignedVersion = $this->entityManager->getRepository(Client::class)
            ->createQueryBuilder('c')
            ->where('c.versionName IS NOT NULL')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $this->assertNotNull($assignedVersion, 'Fixtures should contain a client with a version name.');

        $choices = $this->createForm(ClientType::class, $assignedVersion, self::OPTIONS)
            ->get('versionName')->getConfig()->getOption('choices');

        $this->assertContains($assignedVersion->getVersionName(), $choices);
    }

    public function testVersionNameChoicesExcludeNonProjectBillingVersions(): void
    {
        $nonBillingVersions = array_filter(
            array_map(fn (Version $version) => $version->getName(), $this->entityManager->getRepository(Version::class)->findAll()),
            fn (?string $name) => null !== $name && !str_starts_with($name, ProjectBillingService::PROJECT_BILLING_VERSION_PREFIX)
        );

        $this->assertNotEmpty($nonBillingVersions, 'Fixtures should contain versions outside project billing.');

        $choices = $this->createForm(ClientType::class, new Client(), self::OPTIONS)
            ->get('versionName')->getConfig()->getOption('choices');

        $this->assertSame([], array_intersect($nonBillingVersions, (array) $choices));
    }

    public function testSubmitMapsDataToClient(): void
    {
        $client = new Client();
        $form = $this->createForm(ClientType::class, $client, self::OPTIONS);

        $form->submit([
            'name' => 'Acme Corp',
            'contact' => 'Jane Doe',
            'standardPrice' => '900.5',
            'type' => ClientTypeEnum::EXTERNAL_WITHOUT_MOMS->value,
            'customerKey' => 'CUST-1',
            'psp' => 'PSP-1',
            'ean' => '5790000000000',
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid(), (string) $form->getErrors(true));
        $this->assertSame('Acme Corp', $client->getName());
        $this->assertSame('Jane Doe', $client->getContact());
        $this->assertSame(900.5, $client->getStandardPrice());
        $this->assertSame(ClientTypeEnum::EXTERNAL_WITHOUT_MOMS, $client->getType());
        $this->assertSame('CUST-1', $client->getCustomerKey());
        $this->assertSame('PSP-1', $client->getPsp());
        $this->assertSame('5790000000000', $client->getEan());
    }

    public function testLegacyExternalClientCanKeepItsType(): void
    {
        $client = new Client();
        $client->setType(ClientTypeEnum::EXTERNAL);

        $form = $this->createForm(ClientType::class, $client, self::OPTIONS);
        $form->submit([
            'name' => 'Legacy Corp',
            'contact' => 'John Doe',
            'type' => ClientTypeEnum::EXTERNAL->value,
        ]);

        $this->assertTrue($form->isValid(), (string) $form->getErrors(true));
        $this->assertSame(ClientTypeEnum::EXTERNAL, $client->getType());
    }

    public function testLegacyExternalIsRejectedForOtherClients(): void
    {
        $client = new Client();
        $client->setType(ClientTypeEnum::INTERNAL);

        $form = $this->createForm(ClientType::class, $client, self::OPTIONS);
        $form->submit([
            'name' => 'Modern Corp',
            'contact' => 'John Doe',
            'type' => ClientTypeEnum::EXTERNAL->value,
        ]);

        $this->assertFalse($form->isValid());
    }

    public function testOptionalFieldsAreNotRequired(): void
    {
        $form = $this->createForm(ClientType::class, new Client(), self::OPTIONS);

        foreach (['standardPrice', 'psp', 'ean', 'versionName'] as $field) {
            $this->assertFalse($form->get($field)->isRequired(), sprintf('Field "%s" should be optional.', $field));
        }
    }
}
