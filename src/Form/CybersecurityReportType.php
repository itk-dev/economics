<?php

namespace App\Form;

use App\Entity\DataProvider;
use App\Model\Reports\CybersecurityReportFormData;
use App\Repository\DataProviderRepository;
use App\Service\CybersecurityReportService;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CybersecurityReportType extends AbstractType
{
    private const string DEFAULT_CYBERSECURITY_MILESTONE = 'Cybersikkerhedsaftale';
    public function __construct(
        private readonly CybersecurityReportService $cybersecurityReportService,
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
                'label' => 'cybersecurity_report.data_provider',
                'label_attr' => ['class' => 'label'],
                'placeholder' => 'cybersecurity_report.select_data_provider',
                'attr' => [
                    'onchange' => 'this.form.submit()',
                    'class' => 'form-element',
                ],
                'help' => 'cybersecurity_report.data_provider_helptext',
                'data' => $defaultProvider,
                'choices' => $dataProviders,
            ])
            ->add('version', ChoiceType::class, [
                'choices' => [
                    self::DEFAULT_CYBERSECURITY_MILESTONE => self::DEFAULT_CYBERSECURITY_MILESTONE,
                ],
                'required' => false,
                'attr' => [
                    'class' => 'form-element',
                    'onchange' => 'this.form.submit()',
                ],
                'row_attr' => ['class' => 'form-row form-choices'],
                'placeholder' => 'cybersecurity_report.all_versions',
                'label' => 'cybersecurity_report.version',
                'label_attr' => ['class' => 'label'],
                'data' => self::DEFAULT_CYBERSECURITY_MILESTONE,
            ])
            ->add('fromDate', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime',
                'required' => false,
                'label' => 'cybersecurity_report.from_date',
                'label_attr' => ['class' => 'label'],
                'by_reference' => true,
                'data' => $options['fromDate'] ?? $this->cybersecurityReportService->getDefaultFromDate(),
                'attr' => [
                    'class' => 'form-element',
                    'onchange' => 'this.form.submit()',
                ],
            ])
            ->add('toDate', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime',
                'required' => false,
                'label' => 'cybersecurity_report.to_date',
                'label_attr' => ['class' => 'label'],
                'data' => $options['toDate'] ?? $this->cybersecurityReportService->getDefaultToDate(),
                'by_reference' => true,
                'attr' => [
                    'class' => 'form-element',
                    'onchange' => 'this.form.submit()',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CybersecurityReportFormData::class,
        ])
            ->setRequired('data_provider')
            ->setRequired('version')
        ;
    }
}
