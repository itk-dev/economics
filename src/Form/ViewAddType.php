<?php

namespace App\Form;

use App\Entity\DataProvider;
use App\Entity\View;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ViewAddType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, [
                'required' => true,
                'attr' => ['class' => 'form-element'],
                'label' => 'view.name',
            ])
            ->add('description', null, [
                'required' => true,
                'attr' => ['class' => 'form-element'],
                'label' => 'view.description',
            ])
            ->add('dataProviders', EntityType::class, [
                'class' => DataProvider::class,
                'choice_label' => 'name',
                'multiple' => true,
                'row_attr' => ['class' => 'form-row form-choices'],
                'attr' => [
                    'class' => 'form-element',
                    'data-choices-target' => 'choices',
                ],
                'label' => 'view.data_providers',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => View::class,
        ]);
    }
}
