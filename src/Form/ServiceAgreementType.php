<?php

namespace App\Form;

use App\Entity\ServiceAgreement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ServiceAgreementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('projectId')
            ->add('clientId')
            ->add('cybersecurityAgreementId')
            ->add('hostingProvider')
            ->add('documentUrl')
            ->add('price')
            ->add('projectLeadId')
            ->add('validFrom', null, [
                'widget' => 'single_text',
            ])
            ->add('validTo', null, [
                'widget' => 'single_text',
            ])
            ->add('isActive')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ServiceAgreement::class,
        ]);
    }
}
