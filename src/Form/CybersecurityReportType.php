<?php

namespace App\Form;

use App\Model\Reports\CybersecurityReportFormData;
use App\Service\CybersecurityReportService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<CybersecurityReportFormData>
 */
class CybersecurityReportType extends AbstractType
{
    private const string DEFAULT_CYBERSECURITY_MILESTONE = 'Cybersikkerhedsaftale';

    public function __construct(
        private readonly CybersecurityReportService $cybersecurityReportService,
    ) {
    }

    /**
     * @throws \DateMalformedStringException
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('versionTitle', ChoiceType::class, [
                'choices' => [
                    self::DEFAULT_CYBERSECURITY_MILESTONE => self::DEFAULT_CYBERSECURITY_MILESTONE,
                ],
                'required' => true,
                'attr' => [
                    'class' => 'form-element',
                ],
                'row_attr' => ['class' => 'form-row form-choices'],
                'label' => 'cybersecurity_report.versionTitle',
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
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'workload_report.submit',
                'attr' => [
                    'class' => 'hour-report-submit button',
                ],
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CybersecurityReportFormData::class,
        ])
            ->setRequired('versionTitle');
    }
}
