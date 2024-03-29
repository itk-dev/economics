<?php

namespace App\Form;

use App\Entity\Invoice;
use App\Enum\MaterialNumberEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InvoiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, [
                'required' => true,
                'label' => 'invoices.name',
                'label_attr' => ['class' => 'label'],
                'row_attr' => ['class' => 'form-row'],
                'attr' => ['class' => 'form-element'],
                'help' => 'invoices.name_helptext',
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'invoices.description',
                'label_attr' => ['class' => 'label'],
                'row_attr' => ['class' => 'form-row'],
                'attr' => ['class' => 'form-element', 'data-generate-description-target' => 'description', 'rows' => 4],
                'help' => 'invoices.description_helptext',
            ])
            ->add('client', ChoiceType::class, [
                'required' => true,
                'label' => 'invoices.client',
                'label_attr' => ['class' => 'label'],
                'row_attr' => ['class' => 'form-row form-choices'],
                'attr' => ['class' => 'form-element'],
                'help' => 'invoices.client_helptext',
            ])
            ->add('paidByAccount', ChoiceType::class, [
                'required' => false,
                'label' => 'invoices.paid_by_account',
                'label_attr' => ['class' => 'label'],
                'row_attr' => ['class' => 'form-row form-choices'],
                'attr' => [
                    'class' => 'form-element',
                ],
                'help' => 'invoices.payer_account_helptext',
            ])
            ->add('defaultReceiverAccount', ChoiceType::class, [
                'required' => false,
                'label' => 'invoices.default_receiver_account',
                'label_attr' => ['class' => 'label'],
                'row_attr' => ['class' => 'form-row form-choices'],
                'attr' => [
                    'class' => 'form-element',
                ],
                'help' => 'invoices.default_receiver_account_helptext',
            ])
            ->add('defaultMaterialNumber', EnumType::class, [
                'class' => MaterialNumberEnum::class,
                'required' => false,
                'label' => 'invoices.default_material_number',
                'label_attr' => ['class' => 'label'],
                'row_attr' => ['class' => 'form-row'],
                'attr' => ['class' => 'form-element'],
                'help' => 'invoices.default_material_number_helptext',
                'choice_label' => fn ($choice) => match ($choice) {
                    MaterialNumberEnum::NONE => '',
                    MaterialNumberEnum::INTERNAL => 'material_number_enum.internal',
                    MaterialNumberEnum::EXTERNAL_WITH_MOMS => 'material_number_enum.external_with_moms',
                    MaterialNumberEnum::EXTERNAL_WITHOUT_MOMS => 'material_number_enum.external_without_moms',
                    default => null,
                },
            ])
            ->add('periodFrom', DateType::class, [
                'required' => false,
                'label' => 'invoices.period_from',
                'label_attr' => ['class' => 'label'],
                'row_attr' => ['class' => 'form-row'],
                'help' => 'invoices.period_from_helptext',
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['class' => 'form-element'],
            ])
            ->add('periodTo', DateType::class, [
                'required' => false,
                'label' => 'invoices.period_to',
                'label_attr' => ['class' => 'label'],
                'row_attr' => ['class' => 'form-row'],
                'attr' => ['class' => 'form-element'],
                'help' => 'invoices.period_to_helptext',
                'widget' => 'single_text',
                'html5' => true,
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
