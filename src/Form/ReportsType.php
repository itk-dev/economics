<?php

namespace App\Form;

use App\Model\Reports\ReportsFormData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReportsType extends AbstractType
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
            ]);
        /*         ->add('dateFrom', ChoiceType::class, [
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
              ->add('dateTo', ChoiceType::class, [
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
              ]);*/
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ReportsFormData::class,
            'attr' => [
                'class' => 'form-default',
            ],
        ]);
    }
}
