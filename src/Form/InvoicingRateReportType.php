<?php

namespace App\Form;

use App\Model\Reports\InvoicingRateReportFormData;
use App\Model\Reports\InvoicingRateReportViewModeEnum;
use App\Model\Reports\WorkloadReportPeriodTypeEnum as PeriodTypeEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InvoicingRateReportType extends AbstractType
{
    public function __construct(
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {


        $builder
       /*     ->add('viewMode', EnumType::class, [
                'required' => false,
                'label' => 'workload_report.select_viewmode',
                'label_attr' => ['class' => 'label'],
                'placeholder' => false,
                'attr' => [
                    'class' => 'form-element',
                ],
                'class' => InvoicingRateReportViewModeEnum::class,
            ])*/
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
            'data_class' => InvoicingRateReportFormData::class,
            'attr' => [
                'data-sprint-report-target' => 'form',
            ],
        ]);
    }
}