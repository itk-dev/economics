<?php

namespace App\Tests\Integration\Controller;

use App\Entity\Client;
use App\Entity\CybersecurityAgreement;
use App\Entity\Project;
use App\Entity\ServiceAgreement;
use App\Entity\Worker;
use App\Enum\HostingProviderEnum;

class ServiceAgreementFlowTest extends AbstractTransactionalFlowTestCase
{
    private int $projectId;
    private int $clientId;
    private int $workerId;

    protected function setUp(): void
    {
        $this->bootTransactionalClient('ROLE_ADMIN');

        $this->projectId = $this->requireId($this->findOne(Project::class)->getId());
        $this->clientId = $this->requireId($this->findOne(Client::class)->getId());
        $this->workerId = $this->requireId($this->findOne(Worker::class)->getId());
    }

    public function testIndexListsActiveAgreements(): void
    {
        $this->client->request('GET', '/admin/serviceagreements');

        $this->assertResponseIsSuccessful();
    }

    public function testNewFormIsRendered(): void
    {
        $this->client->request('GET', '/admin/serviceagreements/new');

        $this->assertResponseIsSuccessful();
    }

    public function testNewCreatesAnAgreementWithoutCybersecurity(): void
    {
        $countBefore = $this->countAgreements();

        $this->submitCombinedForm('/admin/serviceagreements/new', attachCybersecurity: false);

        $this->assertResponseRedirects('/admin/serviceagreements');

        $this->entityManager->clear();
        $this->assertSame($countBefore + 1, $this->countAgreements());
        $this->assertNull($this->latestAgreement()->getCybersecurityAgreement());
    }

    public function testNewCreatesAnAgreementWithCybersecurity(): void
    {
        $this->submitCombinedForm('/admin/serviceagreements/new', attachCybersecurity: true);

        $this->assertResponseRedirects('/admin/serviceagreements');

        $this->entityManager->clear();
        $agreement = $this->latestAgreement();
        $cybersecurityAgreement = $this->requireEntity(
            CybersecurityAgreement::class,
            $agreement->getCybersecurityAgreement()
        );
        $this->assertSame(
            $agreement->getId(),
            $this->requireEntity(ServiceAgreement::class, $cybersecurityAgreement->getServiceAgreement())->getId()
        );
    }

    public function testEditFormIsRendered(): void
    {
        $id = $this->persistAgreement();

        $this->client->request('GET', sprintf('/admin/serviceagreements/%d/edit', $id));

        $this->assertResponseIsSuccessful();
    }

    public function testEditUpdatesTheAgreement(): void
    {
        $id = $this->persistAgreement();

        $this->submitCombinedForm(
            sprintf('/admin/serviceagreements/%d/edit', $id),
            attachCybersecurity: false,
            price: '4242'
        );

        $this->assertResponseRedirects('/admin/serviceagreements');

        $this->entityManager->clear();
        $this->assertSame(
            4242.0,
            $this->findById(ServiceAgreement::class, $id)->getPrice()
        );
    }

    public function testEditCanAttachACybersecurityAgreement(): void
    {
        $id = $this->persistAgreement();

        $this->submitCombinedForm(sprintf('/admin/serviceagreements/%d/edit', $id), attachCybersecurity: true);

        $this->assertResponseRedirects('/admin/serviceagreements');

        $this->entityManager->clear();
        $this->assertNotNull(
            $this->findById(ServiceAgreement::class, $id)->getCybersecurityAgreement()
        );
    }

    public function testEditCanDetachACybersecurityAgreement(): void
    {
        $id = $this->persistAgreement(withCybersecurityAgreement: true);

        $this->submitCombinedForm(sprintf('/admin/serviceagreements/%d/edit', $id), attachCybersecurity: false);

        $this->assertResponseRedirects('/admin/serviceagreements');

        $this->entityManager->clear();
        $this->assertNull(
            $this->findById(ServiceAgreement::class, $id)->getCybersecurityAgreement()
        );
        $this->assertCount(0, $this->entityManager->getRepository(CybersecurityAgreement::class)
            ->findBy(['serviceAgreement' => $id]));
    }

    public function testDeleteRemovesTheAgreement(): void
    {
        $id = $this->persistAgreement();

        $this->submitDeleteFormAt(
            sprintf('/admin/serviceagreements/%d/edit', $id),
            '/admin/serviceagreements/'.$id
        );

        $this->assertResponseRedirects('/admin/serviceagreements');

        $this->entityManager->clear();
        $this->assertNull($this->findByIdOrNull(ServiceAgreement::class, $id));
    }

    public function testDeleteWithAnInvalidTokenKeepsTheAgreement(): void
    {
        $id = $this->persistAgreement();

        $this->client->request('POST', '/admin/serviceagreements/'.$id, ['_token' => 'invalid-token']);

        $this->assertResponseRedirects('/admin/serviceagreements');

        $this->entityManager->clear();
        $this->assertNotNull($this->findByIdOrNull(ServiceAgreement::class, $id));
    }

    private function submitCombinedForm(string $url, bool $attachCybersecurity, string $price = '1234'): void
    {
        $crawler = $this->client->request('GET', $url);
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="combined_service_agreement"]')->form();
        $prefix = 'combined_service_agreement[serviceAgreement]';
        $form[$prefix.'[project]'] = (string) $this->projectId;
        $form[$prefix.'[client]'] = (string) $this->clientId;
        $form[$prefix.'[projectLead]'] = (string) $this->workerId;
        $form[$prefix.'[validFrom]'] = '2026-01-01';
        $form[$prefix.'[price]'] = $price;

        $attachField = $this->choiceField($form, 'combined_service_agreement[attachCybersecurityAgreement]');
        if ($attachCybersecurity) {
            $attachField->tick();
        } else {
            $attachField->untick();
        }

        $this->client->submit($form);
    }

    private function countAgreements(): int
    {
        return count($this->entityManager->getRepository(ServiceAgreement::class)->findAll());
    }

    private function latestAgreement(): ServiceAgreement
    {
        return $this->entityManager->getRepository(ServiceAgreement::class)
            ->findBy([], ['id' => 'DESC'], 1)[0];
    }

    private function persistAgreement(bool $withCybersecurityAgreement = false): int
    {
        $agreement = new ServiceAgreement();
        $agreement->setProject($this->entityManager->getRepository(Project::class)->find($this->projectId));
        $agreement->setClient($this->entityManager->getRepository(Client::class)->find($this->clientId));
        $agreement->setProjectLead($this->entityManager->getRepository(Worker::class)->find($this->workerId));
        $agreement->setHostingProvider(HostingProviderEnum::ADM);
        $agreement->setPrice(1000.0);
        $agreement->setValidFrom(new \DateTime('2026-01-01'));
        $agreement->setValidTo(new \DateTime('2026-12-31'));
        $agreement->setIsActive(true);
        $this->entityManager->persist($agreement);
        $this->entityManager->flush();

        if ($withCybersecurityAgreement) {
            $cybersecurityAgreement = new CybersecurityAgreement();
            $cybersecurityAgreement->setServiceAgreement($agreement);
            $cybersecurityAgreement->setQuarterlyHours(10.0);
            $this->entityManager->persist($cybersecurityAgreement);
            $agreement->setCybersecurityAgreement($cybersecurityAgreement);
            $this->entityManager->flush();
        }

        $id = $this->requireId($agreement->getId());
        $this->entityManager->clear();

        return $id;
    }
}
