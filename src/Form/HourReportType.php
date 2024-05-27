<?php

namespace App\Form;

use App\Model\Reports\HourReportFormData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HourReportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dataProvider')
            ->add('projectId', ChoiceType::class, [
                'placeholder' => 'sprint_report.select_an_option',
                'required' => true,
                'label' => 'sprint_report.select_project',
                'label_attr' => ['class' => 'label'],
                'disabled' => true,
                'attr' => [
                    'class' => 'form-element',
                    'data-sprint-report-target' => 'project',
                    'data-action' => 'sprint-report#submitFormProjectId',
                ],
                'row_attr' => ['class' => 'form-row form-choices'],
            ])
            ->add('versionId', ChoiceType::class, [
                'placeholder' => 'sprint_report.select_an_option',
                'required' => false,
                'label' => 'sprint_report.select_version',
                'label_attr' => ['class' => 'label'],
                'disabled' => true,
                'attr' => [
                    'class' => 'form-element',
                    'data-sprint-report-target' => 'version',
                    'data-action' => 'sprint-report#submitForm',
                ],
                'row_attr' => ['class' => 'form-row form-choices'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => HourReportFormData::class,
            'attr' => [
                'data-sprint-report-target' => 'form',
            ],
        ]);
    }
}
