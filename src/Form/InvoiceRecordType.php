<?php

namespace App\Form;

use App\Model\Invoices\ConfirmData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InvoiceRecordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('confirmation', ChoiceType::class, [
                'required' => true,
                'label' => 'invoices.record_invoice',
                'label_attr' => ['class' => 'label'],
                'choices' => [
                    'invoices.record_invoice_no' => ConfirmData::INVOICE_RECORD_NO,
                    'invoices.record_invoice_yes' => ConfirmData::INVOICE_RECORD_YES,
                    'invoices.record_invoice_yes_no_cost' => ConfirmData::INVOICE_RECORD_YES_NO_COST,
                ],
                'help' => 'invoices.record_invoice_helptext',
                'attr' => ['class' => 'form-element'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ConfirmData::class,
        ]);
    }
}
