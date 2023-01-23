<?php

namespace App\Form;

use App\Entity\InvoiceEntry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InvoiceEntryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('description',  null, [
                'required' => true,
                'attr' => ['class' => 'form-element']
            ])
            ->add('account',  null, [
                'required' => true,
                'attr' => ['class' => 'form-element']
            ])
            ->add('product',  null, [
                'required' => true,
                'attr' => ['class' => 'form-element']
            ])
            ->add('price',  null, [
                'required' => true,
                'attr' => ['class' => 'form-element']
            ])
            ->add('amount',  null, [
                'required' => true,
                'attr' => ['class' => 'form-element']
            ])
            ->add('materialNumber',  null, [
                'required' => true,
                'attr' => ['class' => 'form-element']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InvoiceEntry::class,
        ]);
    }
}
