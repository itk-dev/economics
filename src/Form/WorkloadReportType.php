<?php

namespace App\Form;

use App\Entity\DataProvider;
use App\Model\Reports\WorkloadReportFormData;
use App\Model\Reports\WorkloadReportPeriodTypeEnum as PeriodTypeEnum;
use App\Model\Reports\WorkloadReportViewModeEnum;
use App\Repository\DataProviderRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class WorkloadReportType extends AbstractType
{
    public function __construct(
        private readonly DataProviderRepository $dataProviderRepository,
        private readonly ?string $defaultDataProvider,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $dataProviders = $this->dataProviderRepository->findAll();
        $defaultProvider = $this->dataProviderRepository->find($this->defaultDataProvider);

        if (null === $defaultProvider && count($dataProviders) > 0) {
            $defaultProvider = $dataProviders[0];
        }

        $builder
            ->add('dataProvider', EntityType::class, [
                'class' => DataProvider::class,
                'required' => false,
                'label' => 'reports.workload_report.select_data_provider',
                'label_attr' => ['class' => 'label'],
                'attr' => [
                    'class' => 'form-element',
                ],
                'data' => $defaultProvider,
                'choices' => $dataProviders,
            ])
            ->add('viewPeriodType')
            ->add('viewMode', EnumType::class, [
                'required' => false,
                'label' => 'reports.workload_report.select_viewmode',
                'label_attr' => ['class' => 'label'],
                'placeholder' => false,
                'attr' => [
                    'class' => 'form-element',
                ],
                'class' => WorkloadReportViewModeEnum::class,
            ])
            ->add('viewPeriodType', EnumType::class, [
                'required' => false,
                'label' => 'reports.workload_report.select_view_period_type',
                'label_attr' => ['class' => 'label'],
                'placeholder' => false,
                'attr' => [
                    'class' => 'form-element',
                ],
                'class' => PeriodTypeEnum::class,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'reports.workload_report.submit',
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
        ]);
    }
}
