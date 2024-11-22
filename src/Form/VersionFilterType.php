<?php

namespace App\Form;

use App\Model\Invoices\ProjectFilterData;
use App\Model\Invoices\VersionFilterData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VersionFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', SearchType::class, [
                'required' => false,
                'label' => 'version.form_name',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
            ])
            /*->add('project', SearchType::class, [
                'required' => false,
                'label' => 'version.form_project',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
            ])*/
            ->add('isBillable', ChoiceType::class, [
                'required' => true,
                'label' => 'version.is_billable',
                'label_attr' => ['class' => 'label'],
                'choices' => [
                    'version.is_billable_null' => null,
                    'version.is_billable_false' => false,
                    'version.is_billable_true' => true,
                ],
                'attr' => ['class' => 'form-element'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'data_class' => VersionFilterData::class,
        ]);
    }
}
