<?php

namespace App\Form;

use App\Model\Reports\BillableUnbilledHoursReportFormData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BillableUnbilledHoursReportType extends AbstractType
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
        $quarterChoices = [];
        foreach ($options['quarters'] as $quarterKey => $quarter) {
            $quarterChoices[$quarter] = $quarterKey;
        }

        $builder
            ->add('year', ChoiceType::class, [
                'label' => 'billable_unbilled_hours_report.year',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element '],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row'],
                'required' => false,
                'data' => $yearChoices[date('Y')],
                'choices' => $yearChoices,
                'placeholder' => null,
            ])
            ->add('quarter', ChoiceType::class, [
                'label' => 'billable_unbilled_hours_report.quarter',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element '],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row'],
                'required' => false,
                'data' => ceil(date('n') / 3),
                'choices' => $quarterChoices,
                'placeholder' => null,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'billable_unbilled_hours_report.submit',
                'attr' => [
                    'class' => 'hour-report-submit button',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BillableUnbilledHoursReportFormData::class,
            'years' => null,
            'quarters' => null,
        ]);
    }
}
