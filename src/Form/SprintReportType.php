<?php

namespace App\Form;

use App\Entity\Project;
use App\Entity\Version;
use App\Model\SprintReport\SprintReportFormData;
use App\Repository\ProjectRepository;
use App\Repository\VersionRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SprintReportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $project = $options['project'] ?? null;

        $builder
            ->add('project', EntityType::class, [
                'class' => Project::class,
                'required' => false,
                'query_builder' => function (ProjectRepository $projectRepository) {
                    return $projectRepository->getIncluded();
                },
                'attr' => [
                    'onchange' => 'this.form.submit()',
                    'class' => 'form-element',
                    'data-choices-target' => 'choices',
                    'data-sprint-report-target' => 'project',
                ],
                'row_attr' => ['class' => 'form-row form-choices'],
                'choice_label' => function (Project $pr) {
                    return $pr->getName().' ('.$pr->getDataProvider().')';
                },
            ])
            ->add('version', EntityType::class, [
                'class' => Version::class,
                'disabled' => empty($project),
                'required' => false,
                'query_builder' => function (VersionRepository $versionRepository) use ($project) {
                    if (empty($project)) {
                        return null;
                    }

                    return $versionRepository->getQueryBuilderByProject($project);
                },
                'attr' => [
                    'onchange' => 'this.form.submit()',
                    'class' => 'form-element',
                    'data-choices-target' => 'choices',
                    'data-sprint-report-target' => 'version',
                ],
                'row_attr' => ['class' => 'form-row form-choices'],
                'choice_label' => function (Version $version) {
                    return $version->getName();
                },
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SprintReportFormData::class,
            'project' => null,
        ])
            ->setAllowedTypes('project', [Project::class, 'null']);
    }
}
