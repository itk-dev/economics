<?php

namespace App\Tests\Integration\Form;

use App\Entity\CybersecurityAgreement;
use App\Form\CybersecurityAgreementType;

class CybersecurityAgreementTypeTest extends AbstractFormTestCase
{
    public function testFormExposesExpectedFields(): void
    {
        $this->assertHasFields(CybersecurityAgreementType::class, ['quarterlyHours', 'price', 'note']);
    }

    public function testDataClassIsCybersecurityAgreement(): void
    {
        $form = $this->createForm(CybersecurityAgreementType::class);

        $this->assertSame(CybersecurityAgreement::class, $form->getConfig()->getOption('data_class'));
    }

    public function testAllFieldsAreOptional(): void
    {
        $form = $this->createForm(CybersecurityAgreementType::class, new CybersecurityAgreement());

        foreach (['quarterlyHours', 'price', 'note'] as $field) {
            $this->assertFalse($form->get($field)->isRequired(), sprintf('Field "%s" should be optional.', $field));
        }
    }

    public function testSubmitMapsDataToAgreement(): void
    {
        $agreement = new CybersecurityAgreement();
        $form = $this->createForm(CybersecurityAgreementType::class, $agreement);

        $form->submit([
            'quarterlyHours' => '12.5',
            'price' => '4500',
            'note' => 'Quarterly security review.',
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid(), (string) $form->getErrors(true));
        $this->assertSame(12.5, $agreement->getQuarterlyHours());
        $this->assertSame(4500.0, $agreement->getPrice());
        $this->assertSame('Quarterly security review.', $agreement->getNote());
    }

    public function testEmptySubmitLeavesAgreementBlank(): void
    {
        $agreement = new CybersecurityAgreement();
        $form = $this->createForm(CybersecurityAgreementType::class, $agreement);

        $form->submit(['quarterlyHours' => '', 'price' => '', 'note' => '']);

        $this->assertTrue($form->isValid(), (string) $form->getErrors(true));
        $this->assertNull($agreement->getQuarterlyHours());
        $this->assertNull($agreement->getPrice());
        $this->assertNull($agreement->getNote());
    }
}
