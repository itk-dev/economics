<?php

namespace App\Form;

use App\Entity\PriceList;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\NotNull;

class PriceListType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
        ->add('name', TextType::class, [
            'label' => 'create_client_form.price_list_name.label',
            'label_attr' => ['class' => 'label'],
            'constraints' => [
                new NotNull(['groups' => 'base']),
            ],
            'attr' => [
                'class' => 'form-element',
            ],
            'help_attr' => [
                'class' => 'form-help',
            ],
            'help' => 'create_client_form.price_list_name.help',
            'required' => true,
            'row_attr' => ['class' => 'form-element-wrapper'],
        ])
        ->add('price', TextType::class, [
            'label' => 'create_client_form.price_list_price.label',
            'label_attr' => ['class' => 'label'],
            'constraints' => [
                new NotNull(['groups' => 'base']),
            ],
            'attr' => [
                'class' => 'form-element',
            ],
            'help_attr' => [
                'class' => 'form-help',
            ],
            'help' => 'create_client_form.price_list_price.help',
            'required' => true,
            'row_attr' => ['class' => 'form-element-wrapper'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PriceList::class,
        ]);
    }
}
