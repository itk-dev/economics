<?php

namespace App\Form;

use App\Entity\InvoiceEntry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InvoiceEntryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $accounts = $options['invoice_entry_accounts'];
        if (!empty($accounts)) {
            $builder
                ->add('account', ChoiceType::class, [
                    'required' => true,
                    'attr' => ['class' => 'form-element'],
                    'label' => 'invoices.invoice_entry_receiver_account',
                    'label_attr' => ['class' => 'label'],
                    'row_attr' => ['class' => 'form-row'],
                    'help' => 'invoices.invoice_entry_receiver_account_helptext',
                    'choices' => $accounts,
                ]);
        }

        $builder
            ->add('product', null, [
                'required' => true,
                'attr' => ['class' => 'form-element'],
                'label' => 'invoices.invoice_entry_product',
                'label_attr' => ['class' => 'label'],
                'row_attr' => ['class' => 'form-row'],
                'help' => 'invoices.invoice_entry_product_helptext',
            ])
            ->add('price', NumberType::class, [
                'required' => true,
                'attr' => ['class' => 'form-element', 'min' => 0],
                'label' => 'invoices.invoice_entry_price',
                'label_attr' => ['class' => 'label'],
                'row_attr' => ['class' => 'form-row'],
                'help' => 'invoices.invoice_entry_price_helptext',
                'html5' => true,
            ])
            ->add('amount', NumberType::class, [
                'required' => true,
                'attr' => ['class' => 'form-element', 'min' => 0],
                'label' => 'invoices.invoice_entry_amount',
                'label_attr' => ['class' => 'label'],
                'row_attr' => ['class' => 'form-row'],
                'help' => 'invoices.invoice_entry_amount_helptext',
                'html5' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InvoiceEntry::class,
            'invoice_entry_accounts' => [],
        ])
            ->setAllowedTypes('invoice_entry_accounts', ['array'])
            ->setAllowedValues('invoice_entry_accounts', static fn ($value) => is_array($value)
                && (empty($value) || !array_is_list($value)))
        ;
    }
}
