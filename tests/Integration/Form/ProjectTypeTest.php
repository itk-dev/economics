<?php

namespace App\Tests\Integration\Form;

use App\Entity\Project;
use App\Entity\Worker;
use App\Form\ProjectType;

class ProjectTypeTest extends AbstractFormTestCase
{
    public function testFormExposesExpectedFields(): void
    {
        $this->assertHasFields(
            ProjectType::class,
            ['projectLeadName', 'projectLeadMail', 'holidayPlanning', 'githubRepos', 'codeowners']
        );
    }

    public function testDataClassIsProject(): void
    {
        $form = $this->createForm(ProjectType::class);

        $this->assertSame(Project::class, $form->getConfig()->getOption('data_class'));
    }

    public function testProjectLeadFieldsAreRequired(): void
    {
        $form = $this->createForm(ProjectType::class, new Project());

        $this->assertTrue($form->get('projectLeadName')->isRequired());
        $this->assertTrue($form->get('projectLeadMail')->isRequired());
    }

    public function testRemainingFieldsAreOptional(): void
    {
        $form = $this->createForm(ProjectType::class, new Project());

        foreach (['holidayPlanning', 'githubRepos', 'codeowners'] as $field) {
            $this->assertFalse($form->get($field)->isRequired(), sprintf('Field "%s" should be optional.', $field));
        }
    }

    public function testCodeownersIsAMultipleWorkerChoice(): void
    {
        $config = $this->createForm(ProjectType::class)->get('codeowners')->getConfig();

        $this->assertSame(Worker::class, $config->getOption('class'));
        $this->assertTrue($config->getOption('multiple'));
    }

    public function testSubmitMapsDataToProject(): void
    {
        $workerId = $this->requireId($this->findOne(Worker::class)->getId());

        $project = new Project();
        $form = $this->createForm(ProjectType::class, $project);

        $form->submit([
            'projectLeadName' => 'Lead Person',
            'projectLeadMail' => 'lead@example.com',
            'holidayPlanning' => '1',
            'githubRepos' => "itk-dev/economics\nitk-dev/other",
            'codeowners' => [(string) $workerId],
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid(), (string) $form->getErrors(true));
        $this->assertSame('Lead Person', $project->getProjectLeadName());
        $this->assertSame('lead@example.com', $project->getProjectLeadMail());
        $this->assertTrue($project->isHolidayPlanning());
        $this->assertSame("itk-dev/economics\nitk-dev/other", $project->getGithubRepos());
        $this->assertCount(1, $project->getCodeowners());
    }

    public function testProjectLeadMailRendersAsAnEmailInput(): void
    {
        $view = $this->createForm(ProjectType::class, new Project())->createView();

        $this->assertContains('email', $view->children['projectLeadMail']->vars['block_prefixes']);
    }

    public function testSubmitWithoutOptionalFieldsLeavesThemEmpty(): void
    {
        $project = new Project();
        $form = $this->createForm(ProjectType::class, $project);

        $form->submit([
            'projectLeadName' => 'Lead Person',
            'projectLeadMail' => 'lead@example.com',
        ]);

        $this->assertTrue($form->isValid(), (string) $form->getErrors(true));
        $this->assertNull($project->getGithubRepos());
        $this->assertCount(0, $project->getCodeowners());
    }
}
