<?php

namespace App\Form;

use App\Model\Reports\WorkloadReportFormData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class WorkloadReportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dataProvider')
            ->add('viewMode')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => WorkloadReportFormData::class,
            'attr' => [
                'data-sprint-report-target' => 'form',
            ],
        ]);
    }
}
