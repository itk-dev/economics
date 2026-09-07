<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Client;
use App\Entity\CybersecurityAgreement;
use App\Entity\Project;
use App\Entity\ServiceAgreement;
use App\Entity\Worker;
use App\Enum\HostingProviderEnum;
use App\Enum\ServerSizeEnum;
use App\Enum\SystemOwnerNoticeEnum;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class ServiceAgreementTest extends TestCase
{
    private ServiceAgreement $agreement;

    protected function setUp(): void
    {
        $this->agreement = new ServiceAgreement();
    }

    public function testDefaults(): void
    {
        $this->assertFalse($this->agreement->isEol());
        $this->assertFalse($this->agreement->isDedicatedServer());
        $this->assertSame([], $this->agreement->getSystemOwnerNotices());
        $this->assertNull($this->agreement->isActive());
        $this->assertNull($this->agreement->getServerSize());
    }

    public function testRelationAccessors(): void
    {
        $project = new Project();
        $client = new Client();
        $worker = new Worker();
        $cybersecurityAgreement = new CybersecurityAgreement();

        $this->agreement->setProject($project);
        $this->agreement->setClient($client);
        $this->agreement->setProjectLead($worker);
        $this->agreement->setCybersecurityAgreement($cybersecurityAgreement);

        $this->assertSame($project, $this->agreement->getProject());
        $this->assertSame($client, $this->agreement->getClient());
        $this->assertSame($worker, $this->agreement->getProjectLead());
        $this->assertSame($cybersecurityAgreement, $this->agreement->getCybersecurityAgreement());
    }

    public function testRelationAccessorsAcceptNull(): void
    {
        $this->agreement->setProject(null);
        $this->agreement->setClient(null);
        $this->agreement->setProjectLead(null);
        $this->agreement->setCybersecurityAgreement(null);

        $this->assertNull($this->agreement->getProject());
        $this->assertNull($this->agreement->getClient());
        $this->assertNull($this->agreement->getProjectLead());
        $this->assertNull($this->agreement->getCybersecurityAgreement());
    }

    public function testScalarAccessors(): void
    {
        $validFrom = new \DateTime('2026-01-01');
        $validTo = new \DateTime('2026-12-31');

        $this->agreement->setHostingProvider(HostingProviderEnum::DMZ);
        $this->agreement->setDocumentUrl('https://example.com/agreement.pdf');
        $this->agreement->setPrice(12345.5);
        $this->agreement->setValidFrom($validFrom);
        $this->agreement->setValidTo($validTo);
        $this->agreement->setIsActive(true);
        $this->agreement->setIsEol(true);
        $this->agreement->setClientContactName('Jane Doe');
        $this->agreement->setClientContactEmail('jane@example.com');
        $this->agreement->setDedicatedServer(true);
        $this->agreement->setServerSize(ServerSizeEnum::MELLEM);

        $this->assertSame(HostingProviderEnum::DMZ, $this->agreement->getHostingProvider());
        $this->assertSame('https://example.com/agreement.pdf', $this->agreement->getDocumentUrl());
        $this->assertSame(12345.5, $this->agreement->getPrice());
        $this->assertSame($validFrom, $this->agreement->getValidFrom());
        $this->assertSame($validTo, $this->agreement->getValidTo());
        $this->assertTrue($this->agreement->isActive());
        $this->assertTrue($this->agreement->isEol());
        $this->assertSame('Jane Doe', $this->agreement->getClientContactName());
        $this->assertSame('jane@example.com', $this->agreement->getClientContactEmail());
        $this->assertTrue($this->agreement->isDedicatedServer());
        $this->assertSame(ServerSizeEnum::MELLEM, $this->agreement->getServerSize());
    }

    public function testNullableAccessorsAcceptNull(): void
    {
        $this->agreement->setDocumentUrl(null);
        $this->agreement->setValidTo(null);
        $this->agreement->setClientContactName(null);
        $this->agreement->setClientContactEmail(null);
        $this->agreement->setServerSize(null);

        $this->assertNull($this->agreement->getDocumentUrl());
        $this->assertNull($this->agreement->getValidTo());
        $this->assertNull($this->agreement->getClientContactName());
        $this->assertNull($this->agreement->getClientContactEmail());
        $this->assertNull($this->agreement->getServerSize());
    }

    public function testSystemOwnerNoticesRoundTripThroughTheirBackingValues(): void
    {
        $notices = [SystemOwnerNoticeEnum::SERVERFLYTNING, SystemOwnerNoticeEnum::CYBERSIKKERSHEDSOPDATERING];

        $this->agreement->setSystemOwnerNotices($notices);

        $this->assertSame($notices, $this->agreement->getSystemOwnerNotices());
    }

    public function testSystemOwnerNoticesCanBeCleared(): void
    {
        $this->agreement->setSystemOwnerNotices([SystemOwnerNoticeEnum::SIKKERHEDSPATCH]);
        $this->agreement->setSystemOwnerNotices([]);

        $this->assertSame([], $this->agreement->getSystemOwnerNotices());
    }

    public function testValidateValidToAddsAViolationForEolWithoutEndDate(): void
    {
        $this->agreement->setIsEol(true);
        $this->agreement->setValidTo(null);

        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->expects($this->once())->method('atPath')->with('validTo')->willReturnSelf();
        $builder->expects($this->once())->method('addViolation');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->once())
            ->method('buildViolation')
            ->with('service_agreement.valid_to_required_when_eol')
            ->willReturn($builder);

        $this->agreement->validateValidTo($context);
    }

    public function testValidateValidToAcceptsEolWithAnEndDate(): void
    {
        $this->agreement->setIsEol(true);
        $this->agreement->setValidTo(new \DateTime('2026-12-31'));

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        $this->agreement->validateValidTo($context);
    }

    public function testValidateValidToIgnoresNonEolAgreements(): void
    {
        $this->agreement->setIsEol(false);
        $this->agreement->setValidTo(null);

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        $this->agreement->validateValidTo($context);
    }

    public function testSettersAreFluent(): void
    {
        $this->assertSame($this->agreement, $this->agreement->setProject(null));
        $this->assertSame($this->agreement, $this->agreement->setClient(null));
        $this->assertSame($this->agreement, $this->agreement->setProjectLead(null));
        $this->assertSame($this->agreement, $this->agreement->setCybersecurityAgreement(null));
        $this->assertSame($this->agreement, $this->agreement->setHostingProvider(HostingProviderEnum::ADM));
        $this->assertSame($this->agreement, $this->agreement->setDocumentUrl(null));
        $this->assertSame($this->agreement, $this->agreement->setPrice(0.0));
        $this->assertSame($this->agreement, $this->agreement->setValidFrom(new \DateTime()));
        $this->assertSame($this->agreement, $this->agreement->setValidTo(null));
        $this->assertSame($this->agreement, $this->agreement->setIsActive(true));
        $this->assertSame($this->agreement, $this->agreement->setIsEol(false));
        $this->assertSame($this->agreement, $this->agreement->setSystemOwnerNotices([]));
        $this->assertSame($this->agreement, $this->agreement->setClientContactName(null));
        $this->assertSame($this->agreement, $this->agreement->setClientContactEmail(null));
        $this->assertSame($this->agreement, $this->agreement->setDedicatedServer(false));
        $this->assertSame($this->agreement, $this->agreement->setServerSize(null));
    }
}
