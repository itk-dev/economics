<?php

namespace App\Tests\Integration\Form;

use App\Entity\Worker;
use App\Entity\WorkerGroup;
use App\Form\WorkerGroupType;

class WorkerGroupTypeTest extends AbstractFormTestCase
{
    public function testFormExposesExpectedFields(): void
    {
        $this->assertHasFields(WorkerGroupType::class, ['name', 'workers']);
    }

    public function testDataClassIsWorkerGroup(): void
    {
        $form = $this->createForm(WorkerGroupType::class);

        $this->assertSame(WorkerGroup::class, $form->getConfig()->getOption('data_class'));
    }

    public function testNameIsRequiredAndWorkersAreNot(): void
    {
        $form = $this->createForm(WorkerGroupType::class, new WorkerGroup());

        $this->assertTrue($form->get('name')->isRequired());
        $this->assertFalse($form->get('workers')->isRequired());
    }

    public function testWorkersIsAMultipleWorkerChoice(): void
    {
        $config = $this->createForm(WorkerGroupType::class)->get('workers')->getConfig();

        $this->assertSame(Worker::class, $config->getOption('class'));
        $this->assertTrue($config->getOption('multiple'));
    }

    public function testSubmitMapsDataToWorkerGroup(): void
    {
        $workers = $this->entityManager->getRepository(Worker::class)->findBy([], null, 2);
        $this->assertCount(2, $workers, 'Fixtures should contain at least two workers.');

        $group = new WorkerGroup();
        $form = $this->createForm(WorkerGroupType::class, $group);

        $form->submit([
            'name' => 'Group Gamma',
            'workers' => array_map(fn (Worker $worker) => (string) $worker->getId(), $workers),
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid(), (string) $form->getErrors(true));
        $this->assertSame('Group Gamma', $group->getName());
        $this->assertCount(2, $group->getWorkers());
    }

    public function testSubmitWithoutWorkersLeavesGroupEmpty(): void
    {
        $group = new WorkerGroup();
        $form = $this->createForm(WorkerGroupType::class, $group);

        $form->submit(['name' => 'Empty Group']);

        $this->assertTrue($form->isValid(), (string) $form->getErrors(true));
        $this->assertCount(0, $group->getWorkers());
    }
}
