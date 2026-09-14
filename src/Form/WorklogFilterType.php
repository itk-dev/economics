<?php

namespace App\Form;

use App\Entity\DataProvider;
use App\Entity\Project;
use App\Entity\Worker;
use App\Model\Invoices\WorklogFilterData;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<WorklogFilterData>
 */
class WorklogFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('search', SearchType::class, [
                'required' => false,
                'label' => 'worklogs.form_search',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
            ])
            ->add('periodFrom', DateType::class, [
                'required' => false,
                'label' => 'worklog.period_from',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'widget' => 'single_text',
                'html5' => true,
            ])
            ->add('periodTo', DateType::class, [
                'required' => false,
                'label' => 'worklog.period_to',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'widget' => 'single_text',
                'html5' => true,
            ])
            ->add('worker', EntityType::class, [
                'class' => Worker::class,
                'required' => false,
                'label' => 'worklog.worker',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'placeholder' => '',
                // Worker::__toString() falls back email → id, so no choice_label is needed.
                'query_builder' => fn (EntityRepository $er) => $er->createQueryBuilder('worker')
                    ->orderBy('worker.name', 'ASC'),
            ])
            ->add('project', EntityType::class, [
                'class' => Project::class,
                'required' => false,
                'label' => 'worklogs.form_project',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'placeholder' => '',
                'query_builder' => fn (EntityRepository $er) => $er->createQueryBuilder('project')
                    ->orderBy('project.name', 'ASC'),
            ])
            ->add('dataProvider', EntityType::class, [
                'class' => DataProvider::class,
                'required' => false,
                'label' => 'worklogs.form_data_provider',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'placeholder' => '',
                'query_builder' => fn (EntityRepository $er) => $er->createQueryBuilder('data_provider')
                    ->orderBy('data_provider.name', 'ASC'),
            ])
            ->add('isBilled', ChoiceType::class, [
                'required' => false,
                'label' => 'worklog.is_billed',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'placeholder' => 'worklogs.form_is_billed_any',
                'choices' => [
                    'worklog.is_billed_false' => false,
                    'worklog.is_billed_true' => true,
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'data_class' => WorklogFilterData::class,
            // The filter only reads. Without this a token would have to ride along in the query
            // string, which makes the filtered url unshareable and 422s any hand-built one.
            'csrf_protection' => false,
        ]);
    }
}
