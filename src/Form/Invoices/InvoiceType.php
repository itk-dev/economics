<?php

namespace App\Form\Invoices;

use App\Entity\Billing\Invoice;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InvoiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')
            ->add('description')
            ->add('projectId')
            ->add('recorded')
            ->add('customerAccountId')
            ->add('recordedDate')
            ->add('exportedDate')
            ->add('lockedContactName')
            ->add('lockedType')
            ->add('lockedAccountKey')
            ->add('lockedSalesChannel')
            ->add('paidByAccount')
            ->add('defaultPayToAccount')
            ->add('defaultMaterialNumber')
            ->add('periodFrom')
            ->add('periodTo')
            ->add('createdBy')
            ->add('updatedBy')
            ->add('createdAt')
            ->add('updatedAt')
            ->add('project')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Invoice::class,
        ]);
    }
}
