<?php

namespace App\Form;

use App\Model\Invoices\ProjectFilterData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProjectFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', SearchType::class, [
                'required' => false,
                'label' => 'project.form_name',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
            ])
            ->add('key', SearchType::class, [
                'required' => false,
                'label' => 'project.form_key',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
            ])
            ->add('include', ChoiceType::class, [
                'required' => true,
                'label' => 'project.form_include',
                'label_attr' => ['class' => 'label'],
                'choices' => [
                    'project.include_null' => null,
                    'project.include_false' => false,
                    'project.include_true' => true,
                ],
                'attr' => ['class' => 'form-element'],
            ])
            ->add('isBillable', ChoiceType::class, [
                'required' => true,
                'label' => 'project.form_is_billable',
                'label_attr' => ['class' => 'label'],
                'choices' => [
                    'project.is_billable_null' => null,
                    'project.is_billable_false' => false,
                    'project.is_billable_true' => true,
                ],
                'attr' => ['class' => 'form-element'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'data_class' => ProjectFilterData::class,
        ]);
    }
}
