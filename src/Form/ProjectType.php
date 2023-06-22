<?php

namespace App\Form;

use App\Entity\Project;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProjectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')
            ->add('projectTrackerProjectUrl')
            ->add('projectTrackerKey')
            ->add('projectTrackerId')
            ->add('include')
            ->add('createdBy')
            ->add('updatedBy')
            ->add('createdAt')
            ->add('updatedAt')
            ->add('clients')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
        ]);
    }
}
