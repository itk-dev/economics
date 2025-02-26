<?php

namespace App\Form;

use App\Entity\WorkerGroup;
use App\Entity\Worker;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class WorkerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', TextType::class, [
                'label' => 'worker.email',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'required' => false,
                'row_attr' => ['class' => 'form-row'],
            ])
            ->add('name', TextType::class, [
                'label' => 'worker.name',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'required' => false,
                'row_attr' => ['class' => 'form-row'],
            ])
            ->add('workload', TextType::class, [
                'label' => 'worker.workload',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'required' => false,
                'row_attr' => ['class' => 'form-row'],
            ])
            ->add('includeInReports', ChoiceType::class, [
                'label' => 'worker.include_in_reports.title',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'required' => false,
                'row_attr' => ['class' => 'form-row'],
                'choices' => [
                    'worker.include_in_reports.yes' => true,
                    'worker.include_in_reports.no' => false,
                ],
                'multiple' => false,
                'placeholder' => false,
            ])
            ->add('groups', EntityType::class, [
                'class' => WorkerGroup::class,
                'multiple' => true,
                'label' => 'worker.groups',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element', 'data-choices-target' => 'choices'],
                'help_attr' => ['class' => 'form-help'],
                'required' => false,
                'row_attr' => ['class' => 'form-row'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Worker::class,
        ]);
    }
}
