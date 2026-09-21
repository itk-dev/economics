<?php

namespace App\Tests\Integration\Form;

use App\Entity\Worker;
use App\Entity\WorkerGroup;
use App\Form\WorkerType;

class WorkerTypeTest extends AbstractFormTestCase
{
    public function testFormExposesExpectedFields(): void
    {
        $this->assertHasFields(WorkerType::class, ['email', 'name', 'workload', 'includeInReports', 'workerGroups']);
    }

    public function testDataClassIsWorker(): void
    {
        $form = $this->createForm(WorkerType::class);

        $this->assertSame(Worker::class, $form->getConfig()->getOption('data_class'));
    }

    public function testAllFieldsAreOptional(): void
    {
        $form = $this->createForm(WorkerType::class, new Worker());

        foreach (['email', 'name', 'workload', 'includeInReports', 'workerGroups'] as $field) {
            $this->assertFalse($form->get($field)->isRequired(), sprintf('Field "%s" should be optional.', $field));
        }
    }

    public function testIncludeInReportsIsASingleChoiceWithoutPlaceholder(): void
    {
        $config = $this->createForm(WorkerType::class)->get('includeInReports')->getConfig();

        $this->assertSame(
            ['worker.include_in_reports.yes' => true, 'worker.include_in_reports.no' => false],
            $config->getOption('choices')
        );
        $this->assertFalse($config->getOption('multiple'));
        $this->assertNull($config->getOption('placeholder'));
    }

    public function testWorkerGroupsIsMultipleAndNotByReference(): void
    {
        $config = $this->createForm(WorkerType::class)->get('workerGroups')->getConfig();

        $this->assertSame(WorkerGroup::class, $config->getOption('class'));
        $this->assertTrue($config->getOption('multiple'));
        $this->assertFalse($config->getOption('by_reference'));
    }

    public function testSubmitMapsDataToWorker(): void
    {
        $group = $this->findOne(WorkerGroup::class);
        $groupId = $this->requireId($group->getId());

        $worker = new Worker();
        $form = $this->createForm(WorkerType::class, $worker);

        $form->submit([
            'email' => 'new-worker@test.local',
            'name' => 'New Worker',
            'workload' => '37',
            'includeInReports' => $this->choiceValue($form, 'includeInReports', true),
            'workerGroups' => [(string) $groupId],
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid(), (string) $form->getErrors(true));
        $this->assertSame('new-worker@test.local', $worker->getEmail());
        $this->assertSame('New Worker', $worker->getName());
        $this->assertSame(37.0, $worker->getWorkload());
        $this->assertTrue($worker->getIncludeInReports());
        $this->assertCount(1, $worker->getWorkerGroups());
    }

    public function testSubmittingNoGroupsClearsTheOwningSide(): void
    {
        $group = $this->findOne(WorkerGroup::class);

        $worker = new Worker();
        $worker->setEmail('group-clearing@test.local');
        $worker->addWorkerGroup($group);

        $form = $this->createForm(WorkerType::class, $worker);
        $form->submit([
            'email' => 'group-clearing@test.local',
            'includeInReports' => $this->choiceValue($form, 'includeInReports', true),
            'workerGroups' => [],
        ]);

        $this->assertTrue($form->isValid(), (string) $form->getErrors(true));
        $this->assertCount(0, $worker->getWorkerGroups());
        $this->assertFalse($group->getWorkers()->contains($worker));
    }
}
