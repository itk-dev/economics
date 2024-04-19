<?php

namespace App\Form;

use App\Model\Invoices\InvoiceFilterData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InvoiceFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('query', SearchType::class, [
                'required' => false,
                'label' => 'invoices.query_label',
                'label_attr' => ['class' => 'label'],
                'attr' => [
                    'class' => 'form-element',
                    'placeholder' => 'invoices.query_placeholder',
                ],
            ])
            ->add('recorded', ChoiceType::class, [
                'required' => true,
                'label' => 'invoices.recorded',
                'label_attr' => ['class' => 'label'],
                'choices' => [
                    'invoices.recorded_false' => false,
                    'invoices.recorded_true' => true,
                ],
                'attr' => ['class' => 'form-element'],
            ])
            ->add('createdBy', SearchType::class, [
                'required' => false,
                'label' => 'invoices.created_by',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
            ])
            ->add('projectBilling', ChoiceType::class, [
                'required' => true,
                'label' => 'invoices.include_project_billing',
                'label_attr' => ['class' => 'label'],
                'choices' => [
                    'invoices.without_project_billing' => false,
                    'invoices.with_project_billing' => null,
                    'invoices.only_project_billing' => true,
                ],
                'attr' => ['class' => 'form-element'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'data_class' => InvoiceFilterData::class,
        ]);
    }
}
