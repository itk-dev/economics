<?php

namespace App\Form;

use App\Entity\DataProvider;
use App\Entity\Issue;
use App\Entity\Project;
use App\Entity\Version;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class IssueType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')
            ->add('status')
            ->add('accountKey')
            ->add('accountId')
            ->add('projectTrackerId')
            ->add('projectTrackerKey')
            ->add('epicKey')
            ->add('epicName')
            ->add('resolutionDate')
            ->add('createdBy')
            ->add('updatedBy')
            ->add('createdAt')
            ->add('updatedAt')
            ->add('versions', EntityType::class, [
                'class' => Version::class,
                'choice_label' => 'id',
                'multiple' => true,
            ])
            ->add('project', EntityType::class, [
                'class' => Project::class,
                'choice_label' => 'id',
            ])
            ->add('dataProvider', EntityType::class, [
                'class' => DataProvider::class,
                'choice_label' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Issue::class,
        ]);
    }
}
