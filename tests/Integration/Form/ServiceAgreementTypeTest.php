<?php

namespace App\Tests\Integration\Form;

use App\Entity\Client;
use App\Entity\Project;
use App\Entity\ServiceAgreement;
use App\Entity\Worker;
use App\Enum\HostingProviderEnum;
use App\Enum\ServerSizeEnum;
use App\Enum\SystemOwnerNoticeEnum;
use App\Form\ServiceAgreementType;
use Symfony\Component\Form\FormInterface;

class ServiceAgreementTypeTest extends AbstractFormTestCase
{
    private const FIELDS = [
        'project',
        'isActive',
        'isEol',
        'client',
        'clientContactName',
        'clientContactEmail',
        'systemOwnerNotices',
        'validFrom',
        'validTo',
        'hostingProvider',
        'dedicatedServer',
        'serverSize',
        'documentUrl',
        'price',
        'projectLead',
    ];

    public function testFormExposesExpectedFields(): void
    {
        $this->assertHasFields(ServiceAgreementType::class, self::FIELDS);
    }

    public function testConfiguredOptions(): void
    {
        $config = $this->createForm(ServiceAgreementType::class)->getConfig();

        $this->assertSame(ServiceAgreement::class, $config->getOption('data_class'));
        $this->assertTrue($config->getOption('cascade_validation'));
    }

    public function testIsActiveDefaultsToChecked(): void
    {
        $form = $this->createForm(ServiceAgreementType::class);

        $this->assertTrue($form->get('isActive')->getConfig()->getOption('data'));
    }

    public function testOptionalFieldsAreNotRequired(): void
    {
        $form = $this->createForm(ServiceAgreementType::class);

        foreach (['isActive', 'isEol', 'clientContactName', 'clientContactEmail', 'systemOwnerNotices', 'validTo', 'dedicatedServer', 'serverSize', 'documentUrl'] as $field) {
            $this->assertFalse($form->get($field)->isRequired(), sprintf('Field "%s" should be optional.', $field));
        }
    }

    public function testSystemOwnerNoticesOffersEveryEnumCase(): void
    {
        $config = $this->createForm(ServiceAgreementType::class)->get('systemOwnerNotices')->getConfig();

        $this->assertSame(SystemOwnerNoticeEnum::cases(), $config->getOption('choices'));
        $this->assertTrue($config->getOption('multiple'));
        $this->assertTrue($config->getOption('expanded'));
    }

    public function testHostingProviderOffersEveryEnumCase(): void
    {
        $config = $this->createForm(ServiceAgreementType::class)->get('hostingProvider')->getConfig();

        $this->assertSame(HostingProviderEnum::cases(), $config->getOption('choices'));
    }

    public function testServerSizeOffersEveryEnumCase(): void
    {
        $config = $this->createForm(ServiceAgreementType::class)->get('serverSize')->getConfig();

        $this->assertSame(ServerSizeEnum::cases(), $config->getOption('choices'));
    }

    /**
     * @dataProvider choiceLabelProvider
     */
    public function testChoiceLabelsAreTranslationKeys(string $field, \BackedEnum $case, string $expectedLabel): void
    {
        $choiceLabel = $this->createForm(ServiceAgreementType::class)->get($field)->getConfig()->getOption('choice_label');

        $this->assertSame($expectedLabel, $choiceLabel($case));
    }

    /**
     * @return array<string, array{string, \BackedEnum, string}>
     */
    public static function choiceLabelProvider(): array
    {
        return [
            'serverflytning' => ['systemOwnerNotices', SystemOwnerNoticeEnum::SERVERFLYTNING, 'system_owner_notice_enum.serverflytning'],
            'sikkerhedspatch' => ['systemOwnerNotices', SystemOwnerNoticeEnum::SIKKERHEDSPATCH, 'system_owner_notice_enum.sikkerhedspatch'],
            'cybersikkershedsopdatering' => ['systemOwnerNotices', SystemOwnerNoticeEnum::CYBERSIKKERSHEDSOPDATERING, 'system_owner_notice_enum.cybersikkershedsopdatering'],
            'server size lille' => ['serverSize', ServerSizeEnum::LILLE, 'server_size_enum.lille'],
            'server size custom' => ['serverSize', ServerSizeEnum::CUSTOM, 'server_size_enum.custom'],
            'hosting provider' => ['hostingProvider', HostingProviderEnum::HETZNER, 'HETZNER'],
        ];
    }

    /**
     * @dataProvider choiceValueProvider
     */
    public function testChoiceValueFallsBackToNull(string $field): void
    {
        $choiceValue = $this->createForm(ServiceAgreementType::class)->get($field)->getConfig()->getOption('choice_value');

        $this->assertNull($choiceValue(null));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function choiceValueProvider(): array
    {
        return [
            'system owner notices' => ['systemOwnerNotices'],
            'server size' => ['serverSize'],
        ];
    }

    public function testSubmitMapsDataToServiceAgreement(): void
    {
        $projectId = $this->requireId($this->findOne(Project::class)->getId());
        $clientId = $this->requireId($this->findOne(Client::class)->getId());
        $workerId = $this->requireId($this->findOne(Worker::class)->getId());

        $agreement = new ServiceAgreement();
        $form = $this->createForm(ServiceAgreementType::class, $agreement);

        $form->submit([
            'project' => (string) $projectId,
            'client' => (string) $clientId,
            'projectLead' => (string) $workerId,
            'isActive' => '1',
            'isEol' => '1',
            'clientContactName' => 'Contact Person',
            'clientContactEmail' => 'contact@example.com',
            'systemOwnerNotices' => ['serverflytning', 'sikkerhedspatch'],
            'validFrom' => '2026-01-01',
            'validTo' => '2026-12-31',
            'hostingProvider' => $this->choiceValue($form, 'hostingProvider', HostingProviderEnum::HETZNER),
            'dedicatedServer' => '1',
            'serverSize' => 'stor',
            'documentUrl' => 'https://example.com/agreement.pdf',
            'price' => '1234.5',
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid(), (string) $form->getErrors(true));

        $this->assertSame($projectId, $this->requireEntity(Project::class, $agreement->getProject())->getId());
        $this->assertSame($clientId, $this->requireEntity(Client::class, $agreement->getClient())->getId());
        $this->assertSame($workerId, $this->requireEntity(Worker::class, $agreement->getProjectLead())->getId());
        $this->assertTrue($agreement->isActive());
        $this->assertTrue($agreement->isEol());
        $this->assertSame('Contact Person', $agreement->getClientContactName());
        $this->assertSame('contact@example.com', $agreement->getClientContactEmail());
        $this->assertSame(
            [SystemOwnerNoticeEnum::SERVERFLYTNING, SystemOwnerNoticeEnum::SIKKERHEDSPATCH],
            $agreement->getSystemOwnerNotices()
        );
        $this->assertInstanceOf(\DateTimeInterface::class, $agreement->getValidFrom());
        $this->assertInstanceOf(\DateTimeInterface::class, $agreement->getValidTo());
        $this->assertSame('2026-01-01', $agreement->getValidFrom()->format('Y-m-d'));
        $this->assertSame('2026-12-31', $agreement->getValidTo()->format('Y-m-d'));
        $this->assertSame(HostingProviderEnum::HETZNER, $agreement->getHostingProvider());
        $this->assertTrue($agreement->isDedicatedServer());
        $this->assertSame(ServerSizeEnum::STOR, $agreement->getServerSize());
        $this->assertSame('https://example.com/agreement.pdf', $agreement->getDocumentUrl());
        $this->assertSame(1234.5, $agreement->getPrice());
    }

    public function testEolAgreementWithoutValidToIsInvalid(): void
    {
        $form = $this->createForm(ServiceAgreementType::class, new ServiceAgreement());

        $form->submit([
            'project' => (string) $this->requireId($this->findOne(Project::class)->getId()),
            'client' => (string) $this->requireId($this->findOne(Client::class)->getId()),
            'projectLead' => (string) $this->requireId($this->findOne(Worker::class)->getId()),
            'isEol' => '1',
            'validFrom' => '2026-01-01',
            'validTo' => '',
            'hostingProvider' => $this->choiceValue($form, 'hostingProvider', HostingProviderEnum::ADM),
            'price' => '0',
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid());
        $this->assertStringContainsString('service_agreement.valid_to_required_when_eol', (string) $form->getErrors(true));
    }

    public function testMalformedDocumentUrlIsRejected(): void
    {
        $form = $this->submitMinimalAgreement(new ServiceAgreement(), 'http://exa mple.com');

        $this->assertFalse($form->isValid());
        $this->assertGreaterThan(0, $form->get('documentUrl')->getErrors()->count());
    }

    public function testDocumentUrlWithoutSchemeGetsDefaultProtocol(): void
    {
        $agreement = new ServiceAgreement();
        $form = $this->submitMinimalAgreement($agreement, 'example.com');

        $this->assertTrue($form->isValid(), (string) $form->getErrors(true));
        $this->assertSame('http://example.com', $agreement->getDocumentUrl());
    }

    /**
     * @return FormInterface<mixed>
     */
    private function submitMinimalAgreement(ServiceAgreement $agreement, string $documentUrl): FormInterface
    {
        $form = $this->createForm(ServiceAgreementType::class, $agreement);

        $form->submit([
            'project' => (string) $this->requireId($this->findOne(Project::class)->getId()),
            'client' => (string) $this->requireId($this->findOne(Client::class)->getId()),
            'projectLead' => (string) $this->requireId($this->findOne(Worker::class)->getId()),
            'validFrom' => '2026-01-01',
            'hostingProvider' => $this->choiceValue($form, 'hostingProvider', HostingProviderEnum::ADM),
            'documentUrl' => $documentUrl,
            'price' => '0',
        ]);

        return $form;
    }
}
