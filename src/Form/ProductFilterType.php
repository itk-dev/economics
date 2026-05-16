<?php

namespace App\Form;

use App\Entity\Project;
use App\Model\Invoices\ProductFilterData;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', SearchType::class, [
                'required' => false,
                'label' => 'product.form_name',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
            ])
            ->add('project', EntityType::class, [
                'class' => Project::class,
                'required' => false,
                'label' => 'product.form_project',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'data_class' => ProductFilterData::class,
        ]);
    }
}
