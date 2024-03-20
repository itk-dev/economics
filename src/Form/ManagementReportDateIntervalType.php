<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;

class ManagementReportDateIntervalType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dateFrom', DateType::class, [
                'widget' => 'single_text',
                'placeholder' => '',
                'data' => new \DateTime(),
                'attr' => [
                    'min' => $options['data']['firstLog']->format('Y-m-d'),
                    'max' => (new \DateTime())->format('Y-m-d'),
                ],
                'label' => 'reports.from',
            ])
            ->add('dateTo', DateType::class, [
                'widget' => 'single_text',
                'placeholder' => '',
                'data' => new \DateTime(),
                'attr' => [
                    'min' => $options['data']['firstLog']->format('Y-m-d'),
                    'max' => (new \DateTime())->format('Y-m-d'),
                ],
                'label' => 'reports.to',
            ])
            ->add('view', HiddenType::class, [
                'data' => $options['data']['view'],
            ]);
    }
}
