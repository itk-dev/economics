<?php

namespace App\Form;

use App\Entity\DataProvider;
use App\Model\Reports\ForecastReportFormData;
use App\Repository\DataProviderRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ForecastReportType extends AbstractType
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
                'label' => 'workload_report.select_data_provider',
                'label_attr' => ['class' => 'label'],
                'attr' => [
                    'class' => 'form-element',
                ],
                'data' => $defaultProvider,
                'choices' => $dataProviders,
            ])
            ->add('dateFrom', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime',
                'required' => false,
                'label' => 'hour_report.from_date',
                'label_attr' => ['class' => 'label'],
                'by_reference' => true,
                'data' => new \DateTime(),
                'attr' => [
                    'class' => 'form-element',
                    'onchange' => 'this.form.submit()',
                ],
            ])
            ->add('dateTo', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime',
                'required' => false,
                'label' => 'hour_report.to_date',
                'label_attr' => ['class' => 'label'],
                'data' => new \DateTime(),
                'by_reference' => true,
                'attr' => [
                    'class' => 'form-element',
                    'onchange' => 'this.form.submit()',
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'workload_report.submit',
                'attr' => [
                    'class' => 'hour-report-submit button',
                ],
            ]
            );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ForecastReportFormData::class,
            'attr' => [
                'data-sprint-report-target' => 'form',
            ],
        ]);
    }
}
