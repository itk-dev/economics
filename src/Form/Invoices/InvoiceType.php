<?php

namespace App\Form\Invoices;

use App\Entity\Invoice;
use App\Entity\MaterialNumberEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InvoiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name',  null, [
                'required' => true,
                'attr' => ['class' => 'form-element']
            ])
            ->add('description', TextareaType::class, [
                'required' => true,
                'attr' => ['class' => 'form-element', 'rows' => 4]
            ])
            ->add('project',  null, [
                'required' => true,
                'attr' => ['class' => 'form-element']
            ])
            ->add('client',  null, [
                'attr' => ['class' => 'form-element']
            ])
            ->add('payerAccount',  null, [
                'attr' => ['class' => 'form-element']
            ])
            ->add('defaultReceiverAccount',  null, [
                'attr' => ['class' => 'form-element']
            ])
            ->add('defaultMaterialNumber',  EnumType::class, [
                'class' => MaterialNumberEnum::class,
                'choice_label' => fn ($choice) => match ($choice) {
                    MaterialNumberEnum::NONE => '',
                    MaterialNumberEnum::INTERNAL => 'material_number_enum.internal',
                    MaterialNumberEnum::EXTERNAL_WITH_MOMS => 'material_number_enum.external_with_moms',
                    MaterialNumberEnum::EXTERNAL_WITHOUT_MOMS => 'material_number_enum.external_without_moms',
                },
                'attr' => ['class' => 'form-element']
            ])
            ->add('periodFrom',  DateTimeType::class, [
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['class' => 'form-element']
            ])
            ->add('periodTo',  DateTimeType::class, [
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['class' => 'form-element']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Invoice::class,
        ]);
    }
}
