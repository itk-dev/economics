<?php

namespace App\Form;

use App\Entity\DataProvider;
use App\Entity\Project;
use App\Entity\Version;
use App\Model\Reports\HourReportFormData;
use App\Repository\DataProviderRepository;
use App\Repository\ProjectRepository;
use App\Repository\VersionRepository;
use App\Service\HourReportService;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HourReportType extends AbstractType
{
    public function __construct(
        private readonly HourReportService $hourReportService,
        private readonly DataProviderRepository $dataProviderRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dataProvider', EntityType::class, [
                'class' => DataProvider::class,
                'required' => false,
                'label' => 'hour_report.data_provider',
                'label_attr' => ['class' => 'label'],
                'placeholder' => 'hour_report.select_data_provider',
                'attr' => [
                    'onchange' => 'this.form.submit()',
                    'class' => 'form-element',
                ],
                'help' => 'sprint_report.data_provider_helptext',
                'choices' => $this->dataProviderRepository->findAll(),
            ])
            ->add('project', EntityType::class, [
                'class' => Project::class,
                'required' => false,
                'query_builder' => function (ProjectRepository $projectRepository) use ($options) {
                    $query = $projectRepository->getIncluded();
                    if (null !== $options['data_provider']) {
                        $query->where('project.dataProvider = :dataProvider')->setParameter('dataProvider', $options['data_provider']);
                    }

                    return $query;
                },
                'placeholder' => 'hour_report.select_project',
                'choice_label' => function (Project $project) {
                    return $project->getName();
                },
                'attr' => [
                    'class' => 'form-element',
                    'onchange' => 'this.form.submit()',
                ],
                'label' => 'hour_report.project',
                'label_attr' => ['class' => 'label'],
                'disabled' => empty($options['data_provider']),
            ])
            ->add('version', EntityType::class, [
                'class' => Version::class,
                'required' => false,
                'query_builder' => function (VersionRepository $versionRepository) use ($options) {
                    $query = $versionRepository->createQueryBuilder('version');
                    if (null !== $options['project']) {
                        $query->where('version.project = :project')->setParameter('project', $options['project']);
                    }

                    return $query;
                },
                'choice_label' => function (Version $version) {
                    return $version->getName();
                },
                'attr' => [
                    'class' => 'form-element',
                    'onchange' => 'this.form.submit()',
                ],
                'row_attr' => ['class' => 'form-row form-choices'],
                'placeholder' => 'hour_report.all_versions',
                'label' => 'hour_report.version',
                'label_attr' => ['class' => 'label'],
                'disabled' => empty($options['project']),
            ])
            ->add('fromDate', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime',
                'required' => false,
                'label' => 'hour_report.from_date',
                'label_attr' => ['class' => 'label'],
                'by_reference' => true,
                'data' => $options['fromDate'] ?? $this->hourReportService->getDefaultFromDate(),
                'attr' => [
                    'class' => 'form-element',
                    'onchange' => 'this.form.submit()',
                ],
            ])
            ->add('toDate', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime',
                'required' => false,
                'label' => 'hour_report.to_date',
                'label_attr' => ['class' => 'label'],
                'data' => $options['toDate'] ?? $this->hourReportService->getDefaultToDate(),
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
            'data_class' => HourReportFormData::class,
            'attr' => [
                'data-sprint-report-target' => 'form',
            ],
        ])
            ->setRequired('data_provider')
            ->setRequired('project')
            ->setRequired('version')
        ;
    }
}
