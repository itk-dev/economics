<?php

namespace App\Form\Invoices;

use App\Model\Invoices\InvoiceRecordData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InvoiceRecordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('confirmed', ChoiceType::class, [
                'required' => true,
                'label' => 'invoices.record_invoice',
                'label_attr' => ['class' => 'label'],
                'choices' => [
                    'invoices.record_invoice_false' => false,
                    'invoices.record_invoice_true' => true,
                ],
                'help' => 'invoices.record_invoice_helptext',
                'attr' => ['class' => 'form-element']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'data_class' => InvoiceRecordData::class,
        ]);
    }
}
