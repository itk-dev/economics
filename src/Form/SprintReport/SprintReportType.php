<?php

namespace App\Form\SprintReport;

use App\Model\SprintReport\SprintReportFormData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SprintReportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('projectId', ChoiceType::class, [
                'placeholder' => 'select an option',
                'required' => true,
                'label' => 'sprint_report.select_project',
                'attr' => [
                    'data-sprint-report-target' => 'project',
                    'data-action' => 'sprint-report#submitForm',
                ]
            ])
            ->add('versionId', ChoiceType::class, [
                'placeholder' => 'select an option',
                'required' => false,
                'label' => 'sprint_report.select_version',
                'disabled' => true,
                'attr' => [
                    'data-sprint-report-target' => 'version',
                    'data-action' => 'sprint-report#submitForm',
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SprintReportFormData::class,
            'attr' => ['data-sprint-report-target' => 'form'],
        ]);
    }
}
