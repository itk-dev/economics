<?php

namespace App\Tests\Integration\Form;

use App\Form\IssueFilterType;
use App\Model\Invoices\IssueFilterData;

class IssueFilterTypeTest extends AbstractFormTestCase
{
    public function testFormExposesExpectedFields(): void
    {
        $this->assertHasFields(IssueFilterType::class, ['name']);
    }

    public function testFilterIsSubmittedOverGet(): void
    {
        $config = $this->createForm(IssueFilterType::class)->getConfig();

        $this->assertSame('GET', $config->getMethod());
        $this->assertSame(IssueFilterData::class, $config->getOption('data_class'));
    }

    public function testNameIsOptional(): void
    {
        $form = $this->createForm(IssueFilterType::class, new IssueFilterData());

        $this->assertFalse($form->get('name')->isRequired());
    }

    public function testSubmitMapsNameToFilterData(): void
    {
        $data = new IssueFilterData();
        $form = $this->createForm(IssueFilterType::class, $data);

        $form->submit(['name' => 'search term']);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid(), (string) $form->getErrors(true));
        $this->assertSame('search term', $data->name);
    }

    public function testEmptySubmitLeavesNameNull(): void
    {
        $data = new IssueFilterData();
        $form = $this->createForm(IssueFilterType::class, $data);

        $form->submit(['name' => '']);

        $this->assertTrue($form->isValid(), (string) $form->getErrors(true));
        $this->assertNull($data->name);
    }
}
