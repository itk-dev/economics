<?php

namespace App\Tests\Integration\Controller;

use App\Entity\Worker;
use App\Entity\WorkerGroup;

class WorkerFlowTest extends AbstractTransactionalFlowTestCase
{
    protected function setUp(): void
    {
        $this->bootTransactionalClient('ROLE_ADMIN');
    }

    public function testIndexListsWorkers(): void
    {
        $crawler = $this->client->request('GET', '/admin/worker/');

        $this->assertResponseIsSuccessful();
        $this->assertGreaterThan(0, $crawler->filter('a[href*="/admin/worker/"]')->count());
    }

    public function testEditFormIsRendered(): void
    {
        $id = $this->persistWorker('render@test.local');

        $this->client->request('GET', sprintf('/admin/worker/%d/edit', $id));

        $this->assertResponseIsSuccessful();
    }

    public function testEditUpdatesTheWorker(): void
    {
        $id = $this->persistWorker('edit@test.local');

        $this->submitFormAt(sprintf('/admin/worker/%d/edit', $id), 'worker', [
            'worker[email]' => 'edited@test.local',
            'worker[name]' => 'Edited Worker',
            'worker[workload]' => '30',
        ]);

        $this->assertResponseRedirects('/admin/worker/');

        $this->entityManager->clear();
        $worker = $this->findById(Worker::class, $id);
        $this->assertSame('edited@test.local', $worker->getEmail());
        $this->assertSame('Edited Worker', $worker->getName());
        $this->assertSame(30.0, $worker->getWorkload());
    }

    public function testEditCanExcludeAWorkerFromReports(): void
    {
        $id = $this->persistWorker('reports@test.local');

        $crawler = $this->client->request('GET', sprintf('/admin/worker/%d/edit', $id));
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="worker"]')->form();
        $form['worker[email]'] = 'reports@test.local';
        $this->choiceField($form, 'worker[includeInReports]')->select('0');
        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/worker/');

        $this->entityManager->clear();
        $this->assertFalse($this->findById(Worker::class, $id)->getIncludeInReports());
    }

    public function testEditAssignsWorkerGroups(): void
    {
        $id = $this->persistWorker('groups@test.local');
        $group = $this->findOne(WorkerGroup::class);
        $groupId = $this->requireId($group->getId());
        $this->entityManager->clear();

        $this->submitFormAt(sprintf('/admin/worker/%d/edit', $id), 'worker', [
            'worker[email]' => 'groups@test.local',
            'worker[workerGroups]' => [(string) $groupId],
        ]);

        $this->assertResponseRedirects('/admin/worker/');

        $this->entityManager->clear();
        $worker = $this->findById(Worker::class, $id);
        $this->assertCount(1, $worker->getWorkerGroups());
        $this->assertSame($groupId, $this->requireEntity(WorkerGroup::class, $worker->getWorkerGroups()->first() ?: null)->getId());
    }

    public function testDeleteWithAnInvalidTokenKeepsTheWorker(): void
    {
        $id = $this->persistWorker('keep@test.local');

        $this->client->request('POST', '/admin/worker/'.$id, ['_token' => 'invalid-token']);

        $this->assertResponseRedirects('/admin/worker/');

        $this->entityManager->clear();
        $this->assertNotNull($this->findByIdOrNull(Worker::class, $id));
    }

    private function persistWorker(string $email): int
    {
        $worker = new Worker();
        $worker->setEmail($email);
        $worker->setName('Flow Worker');
        $worker->setWorkload(37.0);
        $this->entityManager->persist($worker);
        $this->entityManager->flush();
        $id = $this->requireId($worker->getId());
        $this->entityManager->clear();

        return $id;
    }
}
