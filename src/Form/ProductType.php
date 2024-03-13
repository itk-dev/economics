<?php

namespace App\Form;

use App\Entity\Product;
use App\Entity\Project;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('project', EntityType::class, [
                'placeholder' => new TranslatableMessage('product.select_project'),
                'class' => Project::class,
                'choice_label' => 'name',
                'required' => true,
                'attr' => ['class' => 'form-element'],
                'label' => 'product.project',
                'label_attr' => ['class' => 'label'],
                'row_attr' => ['class' => 'form-row'],
                'help' => 'product.project_helptext',
            ])
            ->add('name', null, [
                'required' => true,
                'attr' => ['class' => 'form-element'],
                'label' => 'product.name',
                'label_attr' => ['class' => 'label'],
                'row_attr' => ['class' => 'form-row'],
                'help' => 'product.name_helptext',
            ])
            ->add('price', NumberType::class, [
                'html5' => true,
                'scale' => 2,
                'required' => true,
                'attr' => [
                    'class' => 'form-element',
                    'step' => '0.01',
                ],
                'label' => 'product.price',
                'label_attr' => ['class' => 'label'],
                'row_attr' => ['class' => 'form-row'],
                'help' => 'product.price_helptext',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);
    }
}
