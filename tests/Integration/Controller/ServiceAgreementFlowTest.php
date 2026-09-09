<?php

namespace App\Tests\Integration\Controller;

use App\Entity\Client;
use App\Entity\ContactRole;
use App\Entity\CybersecurityAgreement;
use App\Entity\Project;
use App\Entity\ServiceAgreement;
use App\Entity\ServiceAgreementContact;
use App\Entity\Worker;
use App\Enum\HostingProviderEnum;

class ServiceAgreementFlowTest extends AbstractTransactionalFlowTestCase
{
    private int $projectId;
    private int $clientId;
    private int $workerId;

    protected function setUp(): void
    {
        parent::setUp();

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

    public function testNewCreatesAnAgreementWithContacts(): void
    {
        $this->submitCombinedForm('/admin/serviceagreements/new', attachCybersecurity: false, contacts: [
            ['name' => 'Anna Hansen', 'email' => 'anna@example.com', 'roles' => ['Fakturering']],
            ['name' => 'Bo Jensen', 'email' => 'bo@example.com', 'roles' => ['Fakturering', 'Daglig kontakt']],
        ]);

        $this->assertResponseRedirects('/admin/serviceagreements');

        $this->entityManager->clear();
        $contacts = $this->latestAgreement()->getContacts();
        $this->assertCount(2, $contacts);

        $first = $contacts->first();
        $this->assertInstanceOf(ServiceAgreementContact::class, $first);
        $this->assertSame('Anna Hansen', $first->getName());
        $this->assertSame('anna@example.com', $first->getEmail());
        $this->assertSame(['Fakturering'], $this->roleNames($first));

        $last = $contacts->last();
        $this->assertInstanceOf(ServiceAgreementContact::class, $last);
        $this->assertSame(['Daglig kontakt', 'Fakturering'], $this->roleNames($last));
    }

    public function testARoleTypedOnTwoContactsIsStoredOnce(): void
    {
        $this->submitCombinedForm('/admin/serviceagreements/new', attachCybersecurity: false, contacts: [
            ['name' => 'Anna Hansen', 'roles' => ['Fakturering']],
            ['name' => 'Bo Jensen', 'roles' => ['Fakturering']],
        ]);

        $this->assertResponseRedirects('/admin/serviceagreements');

        $this->entityManager->clear();
        $this->assertCount(
            1,
            $this->entityManager->getRepository(ContactRole::class)->findBy(['name' => 'Fakturering'])
        );
    }

    public function testATypedRoleIsOfferedOnTheNextAgreement(): void
    {
        $this->submitCombinedForm('/admin/serviceagreements/new', attachCybersecurity: false, contacts: [
            ['name' => 'Anna Hansen', 'roles' => ['Serverdrift']],
        ]);

        $crawler = $this->client->request('GET', '/admin/serviceagreements/new');

        $this->assertResponseIsSuccessful();

        // A new agreement renders no contact rows, so the role options live only
        // in the collection prototype the add button stamps out.
        $prototype = $crawler->filter('[data-prototype]')->attr('data-prototype');
        $this->assertStringContainsString(
            'Serverdrift',
            (string) $prototype,
            'A role created on one agreement should be suggested on the next.'
        );
    }

    public function testEditRemovesAContact(): void
    {
        $id = $this->persistAgreement();
        $this->submitCombinedForm(sprintf('/admin/serviceagreements/%d/edit', $id), attachCybersecurity: false, contacts: [
            ['name' => 'Anna Hansen', 'roles' => []],
        ]);
        $this->entityManager->clear();
        $this->assertCount(1, $this->findById(ServiceAgreement::class, $id)->getContacts());

        // Submitting no rows at all must orphan-remove the one that was there.
        $this->submitCombinedForm(sprintf('/admin/serviceagreements/%d/edit', $id), attachCybersecurity: false, contacts: []);

        $this->assertResponseRedirects('/admin/serviceagreements');

        $this->entityManager->clear();
        $this->assertCount(0, $this->findById(ServiceAgreement::class, $id)->getContacts());
        $this->assertCount(0, $this->entityManager->getRepository(ServiceAgreementContact::class)
            ->findBy(['serviceAgreement' => $id]));
    }

    public function testIndexShowsTheContactCount(): void
    {
        $this->submitCombinedForm('/admin/serviceagreements/new', attachCybersecurity: false, contacts: [
            ['name' => 'Anna Hansen', 'roles' => []],
            ['name' => 'Bo Jensen', 'roles' => []],
        ]);

        $crawler = $this->client->request('GET', '/admin/serviceagreements');

        $this->assertResponseIsSuccessful();
        $this->assertGreaterThan(
            0,
            $crawler->filter('dialog')->count(),
            'Each row with contacts should carry a dialog listing them.'
        );
        $this->assertStringContainsString('Anna Hansen', (string) $this->client->getResponse()->getContent());
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

    /**
     * @param list<array{name: string, email?: string, roles: string[]}>|null $contacts
     */
    private function submitCombinedForm(string $url, bool $attachCybersecurity, string $price = '1234', ?array $contacts = null): void
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

        if (null === $contacts) {
            $this->client->submit($form);

            return;
        }

        // Contact rows are added client-side from the collection prototype, and
        // BrowserKit cannot tick fields that are not in the rendered HTML, so the
        // rows go straight into the payload instead.
        $values = $form->getPhpValues();
        $values['combined_service_agreement']['serviceAgreement']['contacts'] = $contacts;

        $this->client->request($form->getMethod(), $form->getUri(), $values);
    }

    /**
     * @return string[]
     */
    private function roleNames(ServiceAgreementContact $contact): array
    {
        $names = $contact->getRoles()
            ->map(fn (ContactRole $role) => $role->getName())
            ->toArray();
        sort($names);

        return $names;
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
