<?php

namespace App\Form;

use App\Model\Invoices\ClientFilterData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClientFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', SearchType::class, [
                'required' => false,
                'label' => 'client.name',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
            ])
            ->add('contact', SearchType::class, [
                'required' => false,
                'label' => 'client.contact',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'data_class' => ClientFilterData::class,
        ]);
    }
}
