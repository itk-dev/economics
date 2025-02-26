<?php

namespace App\Form;

use App\Entity\Worker;
use App\Entity\WorkerGroup;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class WorkerGroupType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'groups.name_label',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'help' => 'groups.name_help',
                'required' => true,
                'row_attr' => ['class' => 'form-row'],
            ])
            ->add('workers', EntityType::class, [
                'class' => Worker::class,
                'multiple' => true,
                'label' => 'groups.workers',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element', 'data-choices-target' => 'choices'],
                'help_attr' => ['class' => 'form-help'],
                'required' => false,
                'row_attr' => ['class' => 'form-row'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => WorkerGroup::class,
        ]);
    }
}
