<?php

namespace App\Form\Invoices;

use App\Entity\InvoiceEntry;
use App\Enum\MaterialNumberEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InvoiceEntryWorklogType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('materialNumber', EnumType::class, [
                'class' => MaterialNumberEnum::class,
                'required' => true,
                'attr' => ['class' => 'form-element'],
                'label' => 'invoices.invoice_entry_material_number',
                'label_attr' => ['class' => 'label'],
                'row_attr' => ['class' => 'form-row'],
                'help' => 'invoices.invoice_entry_material_number_helptext',
                'choice_label' => fn ($choice) => match ($choice) {
                    MaterialNumberEnum::NONE => '',
                    MaterialNumberEnum::INTERNAL => 'material_number_enum.internal',
                    MaterialNumberEnum::EXTERNAL_WITH_MOMS => 'material_number_enum.external_with_moms',
                    MaterialNumberEnum::EXTERNAL_WITHOUT_MOMS => 'material_number_enum.external_without_moms',
                    default => null,
                },
            ])
            ->add('account', null, [
                'required' => true,
                'attr' => ['class' => 'form-element'],
                'label' => 'invoices.invoice_entry_receiver_acccount',
                'label_attr' => ['class' => 'label'],
                'row_attr' => ['class' => 'form-row'],
                'help' => 'invoices.invoice_entry_receiver_account_helptext',
            ])
            ->add('product', null, [
                'required' => true,
                'attr' => ['class' => 'form-element'],
                'label' => 'invoices.invoice_entry_product',
                'label_attr' => ['class' => 'label'],
                'row_attr' => ['class' => 'form-row'],
                'help' => 'invoices.invoice_entry_product_helptext',
            ])
            ->add('price', IntegerType::class, [
                'required' => true,
                'attr' => ['class' => 'form-element', 'min' => 0],
                'label' => 'invoices.invoice_entry_price',
                'label_attr' => ['class' => 'label'],
                'row_attr' => ['class' => 'form-row'],
                'help' => 'invoices.invoice_entry_price_helptext',
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
