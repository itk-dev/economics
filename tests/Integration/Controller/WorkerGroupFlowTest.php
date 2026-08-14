<?php

namespace App\Tests\Integration\Controller;

use App\Entity\Worker;
use App\Entity\WorkerGroup;

class WorkerGroupFlowTest extends AbstractTransactionalFlowTestCase
{
    protected function setUp(): void
    {
        $this->bootTransactionalClient('ROLE_ADMIN');
    }

    public function testIndexListsGroups(): void
    {
        $crawler = $this->client->request('GET', '/admin/group/');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Group Alpha', $crawler->html());
    }

    public function testNewFormIsRendered(): void
    {
        $this->client->request('GET', '/admin/group/new');

        $this->assertResponseIsSuccessful();
    }

    public function testNewCreatesAGroup(): void
    {
        $this->submitFormAt('/admin/group/new', 'worker_group', [
            'worker_group[name]' => 'Group Created By Test',
        ]);

        $this->assertResponseRedirects('/admin/group/');

        $this->entityManager->clear();
        $this->findOne(WorkerGroup::class, ['name' => 'Group Created By Test']);
    }

    public function testEditFormIsRendered(): void
    {
        $group = $this->persistGroup('Group To Render');

        $this->client->request('GET', sprintf('/admin/group/%d/edit', $group));

        $this->assertResponseIsSuccessful();
    }

    public function testEditRenamesAGroup(): void
    {
        $group = $this->persistGroup('Group To Rename');

        $this->submitFormAt(sprintf('/admin/group/%d/edit', $group), 'worker_group', [
            'worker_group[name]' => 'Group Renamed',
        ]);

        $this->assertResponseRedirects('/admin/group/');

        $this->entityManager->clear();
        $this->assertSame(
            'Group Renamed',
            $this->findById(WorkerGroup::class, $group)->getName()
        );
    }

    public function testEditAssignsWorkers(): void
    {
        $group = $this->persistGroup('Group For Workers');
        $workerId = $this->requireId($this->findOne(Worker::class)->getId());
        $this->entityManager->clear();

        $this->submitFormAt(sprintf('/admin/group/%d/edit', $group), 'worker_group', [
            'worker_group[name]' => 'Group For Workers',
            'worker_group[workers]' => [(string) $workerId],
        ]);

        $this->assertResponseRedirects('/admin/group/');

        $this->entityManager->clear();
        $reloaded = $this->findById(WorkerGroup::class, $group);
        $this->assertCount(1, $reloaded->getWorkers());
        $this->assertSame($workerId, $this->requireEntity(Worker::class, $reloaded->getWorkers()->first() ?: null)->getId());
    }

    public function testDeleteRemovesTheGroup(): void
    {
        $group = $this->persistGroup('Group To Delete');

        $this->submitDeleteFormAt(sprintf('/admin/group/%d/edit', $group), '/admin/group/'.$group);

        $this->assertResponseRedirects('/admin/group/');

        $this->entityManager->clear();
        $this->assertNull($this->findByIdOrNull(WorkerGroup::class, $group));
    }

    public function testDeleteWithAnInvalidTokenKeepsTheGroup(): void
    {
        $group = $this->persistGroup('Group To Keep');

        $this->client->request('POST', '/admin/group/'.$group, ['_token' => 'invalid-token']);

        $this->assertResponseRedirects('/admin/group/');

        $this->entityManager->clear();
        $this->assertNotNull($this->findByIdOrNull(WorkerGroup::class, $group));
    }

    private function persistGroup(string $name): int
    {
        $group = new WorkerGroup();
        $group->setName($name);
        $this->entityManager->persist($group);
        $this->entityManager->flush();
        $id = $this->requireId($group->getId());
        $this->entityManager->clear();

        return $id;
    }
}
