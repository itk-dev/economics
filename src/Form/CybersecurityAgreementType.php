<?php

namespace App\Form;

use App\Entity\CybersecurityAgreement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CybersecurityAgreementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('quarterlyHours', null, [
                'label' => 'serviceAgreement.quarterly_hours',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row']
            ])
            ->add('note', null, [
                'label' => 'serviceAgreement.note',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CybersecurityAgreement::class,
        ]);
    }
}
