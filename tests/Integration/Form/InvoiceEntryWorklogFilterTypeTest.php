<?php

namespace App\Tests\Integration\Form;

use App\Form\InvoiceEntryWorklogFilterType;
use App\Model\Invoices\InvoiceEntryWorklogsFilterData;

class InvoiceEntryWorklogFilterTypeTest extends AbstractFormTestCase
{
    public function testFormExposesExpectedFields(): void
    {
        $this->assertHasFields(
            InvoiceEntryWorklogFilterType::class,
            ['isBilled', 'periodFrom', 'periodTo', 'worker', 'onlyAvailable']
        );
    }

    public function testFilterIsSubmittedOverGet(): void
    {
        $config = $this->createForm(InvoiceEntryWorklogFilterType::class)->getConfig();

        $this->assertSame('GET', $config->getMethod());
        $this->assertSame(InvoiceEntryWorklogsFilterData::class, $config->getOption('data_class'));
    }

    public function testOnlyAvailableIsTheOnlyRequiredField(): void
    {
        $form = $this->createForm(InvoiceEntryWorklogFilterType::class, new InvoiceEntryWorklogsFilterData());

        $this->assertTrue($form->get('onlyAvailable')->isRequired());

        foreach (['isBilled', 'periodFrom', 'periodTo', 'worker'] as $field) {
            $this->assertFalse($form->get($field)->isRequired(), sprintf('Field "%s" should be optional.', $field));
        }
    }

    public function testPeriodDefaultsAreNullWithoutOptions(): void
    {
        $form = $this->createForm(InvoiceEntryWorklogFilterType::class);

        $this->assertNull($form->get('periodFrom')->getConfig()->getOption('data'));
        $this->assertNull($form->get('periodTo')->getConfig()->getOption('data'));
    }

    public function testPeriodOptionsPreFillTheDateFields(): void
    {
        $from = new \DateTime('2026-01-01');
        $to = new \DateTime('2026-03-31');

        $form = $this->createForm(InvoiceEntryWorklogFilterType::class, null, [
            'periodFrom' => $from,
            'periodTo' => $to,
        ]);

        $this->assertSame($from, $form->get('periodFrom')->getConfig()->getOption('data'));
        $this->assertSame($to, $form->get('periodTo')->getConfig()->getOption('data'));
    }

    public function testDateFieldsUseSingleTextHtml5Widgets(): void
    {
        $form = $this->createForm(InvoiceEntryWorklogFilterType::class);

        foreach (['periodFrom', 'periodTo'] as $field) {
            $config = $form->get($field)->getConfig();
            $this->assertSame('single_text', $config->getOption('widget'));
            $this->assertTrue($config->getOption('html5'));
        }
    }

    public function testBooleanChoicesUseTranslationKeys(): void
    {
        $form = $this->createForm(InvoiceEntryWorklogFilterType::class);

        $this->assertSame(
            ['worklog.is_billed_false' => false, 'worklog.is_billed_true' => true],
            $form->get('isBilled')->getConfig()->getOption('choices')
        );
        $this->assertSame(
            ['worklog.only_available_true' => true, 'worklog.only_available_false' => false],
            $form->get('onlyAvailable')->getConfig()->getOption('choices')
        );
    }

    public function testSubmitMapsDataToFilterData(): void
    {
        $data = new InvoiceEntryWorklogsFilterData();
        $form = $this->createForm(InvoiceEntryWorklogFilterType::class, $data);

        $form->submit([
            'isBilled' => $this->choiceValue($form, 'isBilled', true),
            'periodFrom' => '2026-02-01',
            'periodTo' => '2026-02-28',
            'worker' => 'worker@test.local',
            'onlyAvailable' => $this->choiceValue($form, 'onlyAvailable', false),
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid(), (string) $form->getErrors(true));
        $this->assertTrue($data->isBilled);
        $this->assertInstanceOf(\DateTime::class, $data->periodFrom);
        $this->assertInstanceOf(\DateTime::class, $data->periodTo);
        $this->assertSame('2026-02-01', $data->periodFrom->format('Y-m-d'));
        $this->assertSame('2026-02-28', $data->periodTo->format('Y-m-d'));
        $this->assertSame('worker@test.local', $data->worker);
        $this->assertFalse($data->onlyAvailable);
    }

    public function testEmptySubmitClearsOptionalFilters(): void
    {
        $data = new InvoiceEntryWorklogsFilterData();
        $form = $this->createForm(InvoiceEntryWorklogFilterType::class, $data);

        $form->submit([
            'isBilled' => '',
            'periodFrom' => '',
            'periodTo' => '',
            'worker' => '',
            'onlyAvailable' => $this->choiceValue($form, 'onlyAvailable', true),
        ]);

        $this->assertTrue($form->isValid(), (string) $form->getErrors(true));
        $this->assertNull($data->isBilled);
        $this->assertNull($data->periodFrom);
        $this->assertNull($data->periodTo);
        $this->assertNull($data->worker);
        $this->assertTrue($data->onlyAvailable);
    }
}
