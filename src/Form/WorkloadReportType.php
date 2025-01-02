<?php

namespace App\Form;

use App\Model\Reports\WorkloadReportFormData;
use App\Model\Reports\WorkloadReportPeriodTypeEnum as PeriodTypeEnum;
use App\Model\Reports\WorkloadReportViewModeEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class WorkloadReportType extends AbstractType
{
    public function __construct(
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $yearChoices = [];
        foreach ($options['years'] as $year) {
            $yearChoices[$year] = $year;
        }
        $builder
            ->add('year', ChoiceType::class, [
                'label' => 'invoicing_rate_report.year',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element '],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row'],
                'required' => false,
                'data' => $yearChoices[date('Y')],
                'choices' => $yearChoices,
                'placeholder' => null,
            ])
            ->add('viewMode', EnumType::class, [
                'required' => false,
                'label' => 'workload_report.select_viewmode',
                'label_attr' => ['class' => 'label'],
                'placeholder' => false,
                'attr' => [
                    'class' => 'form-element',
                ],
                'class' => WorkloadReportViewModeEnum::class,
            ])
            ->add('viewPeriodType', EnumType::class, [
                'required' => false,
                'label' => 'workload_report.select_view_period_type',
                'label_attr' => ['class' => 'label'],
                'placeholder' => false,
                'attr' => [
                    'class' => 'form-element',
                ],
                'class' => PeriodTypeEnum::class,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'workload_report.submit',
                'attr' => [
                    'class' => 'hour-report-submit button',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => WorkloadReportFormData::class,
            'attr' => [
                'data-sprint-report-target' => 'form',
            ],
            'years' => null,
        ]);
    }
}
