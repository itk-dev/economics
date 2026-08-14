<?php

namespace App\Tests\Integration\Form;

use App\Entity\CybersecurityAgreement;
use App\Entity\ServiceAgreement;
use App\Form\CombinedServiceAgreementType;
use App\Form\CybersecurityAgreementType;
use App\Form\ServiceAgreementType;

class CombinedServiceAgreementTypeTest extends AbstractFormTestCase
{
    public function testFormExposesExpectedFields(): void
    {
        $this->assertHasFields(
            CombinedServiceAgreementType::class,
            ['serviceAgreement', 'attachCybersecurityAgreement', 'cybersecurityAgreement']
        );
    }

    public function testFormIsNotBoundToADataClass(): void
    {
        $form = $this->createForm(CombinedServiceAgreementType::class);

        $this->assertNull($form->getConfig()->getOption('data_class'));
    }

    public function testChildFormsUseTheDedicatedTypes(): void
    {
        $form = $this->createForm(CombinedServiceAgreementType::class);

        $this->assertInstanceOf(
            ServiceAgreementType::class,
            $form->get('serviceAgreement')->getConfig()->getType()->getInnerType()
        );
        $this->assertInstanceOf(
            CybersecurityAgreementType::class,
            $form->get('cybersecurityAgreement')->getConfig()->getType()->getInnerType()
        );
    }

    public function testAttachCheckboxIsUnmappedAndOptional(): void
    {
        $config = $this->createForm(CombinedServiceAgreementType::class)->get('attachCybersecurityAgreement')->getConfig();

        $this->assertFalse($config->getOption('mapped'));
        $this->assertFalse($config->getOption('required'));
    }

    public function testAttachCheckboxDefaultsToUnchecked(): void
    {
        $form = $this->createForm(CombinedServiceAgreementType::class);

        $this->assertFalse($form->get('attachCybersecurityAgreement')->getConfig()->getOption('data'));
    }

    public function testAttachCheckboxIsPreCheckedFromData(): void
    {
        $form = $this->createForm(CombinedServiceAgreementType::class, [
            'serviceAgreement' => new ServiceAgreement(),
            'cybersecurityAgreement' => new CybersecurityAgreement(),
            'attachCybersecurityAgreement' => true,
        ]);

        $this->assertTrue($form->get('attachCybersecurityAgreement')->getConfig()->getOption('data'));
    }

    public function testChildLabelsAreSuppressed(): void
    {
        $form = $this->createForm(CombinedServiceAgreementType::class);

        $this->assertFalse($form->get('serviceAgreement')->getConfig()->getOption('label'));
        $this->assertFalse($form->get('cybersecurityAgreement')->getConfig()->getOption('label'));
    }
}
