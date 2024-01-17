<?php

namespace App\Form;

use App\Entity\Client;
use App\Enum\ClientTypeEnum;
use App\Enum\MaterialNumberEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Contracts\Translation\TranslatorInterface;

class ClientType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'create_client_form.client_name.label',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'help' => 'create_client_form.client_name.help',
                'required' => true,
                'row_attr' => ['class' => 'form-element-wrapper'],
            ])
            ->add('contact', TextType::class, [
                'label' => 'create_client_form.client_contact.label',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'help' => 'create_client_form.client_contact.help',
                'required' => true,
                'row_attr' => ['class' => 'form-element-wrapper'],
            ])
            ->add('standardPrice', NumberType::class, [
                'label' => 'create_client_form.client_standardPrice.label',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element', 'min' => 0],
                'help_attr' => ['class' => 'form-help'],
                'help' => 'create_client_form.client_standardPrice.help',
                'required' => true,
                'row_attr' => ['class' => 'form-element-wrapper'],
                'html5' => true,
            ])
            ->add('type', EnumType::class, [
                'class' => ClientTypeEnum::class,
                'label' => 'create_client_form.type.label',
                'label_attr' => ['class' => 'label'],
                'choice_label' => fn ($choice) => match ($choice) {
                    ClientTypeEnum::INTERNAL => 'client_type_enum.internal',
                    ClientTypeEnum::EXTERNAL => 'client_type_enum.external',
                },
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'help' => 'create_client_form.type.help',
                'required' => true,
                'row_attr' => ['class' => 'form-element-wrapper'],
            ])
            ->add('account', TextType::class, [
                'label' => 'create_client_form.client_account.label',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'help' => 'create_client_form.client_account.help',
                'required' => true,
                'row_attr' => ['class' => 'form-element-wrapper'],
            ])
            ->add('salesChannel', ChoiceType::class, [
                'label' => 'create_client_form.sales_channel.label',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'help' => 'create_client_form.sales_channel.help',
                'required' => true,
                'row_attr' => ['class' => 'form-element-wrapper'],
                'choices' => [
                    10 => 10,
                    20 => 20,
                ]
            ])
            ->add('customerKey', TextType::class, [
                'label' => 'create_client_form.customer_key.label',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'help' => 'create_client_form.customer_key.help',
                'row_attr' => ['class' => 'form-element-wrapper'],
            ])
            ->add('psp', TextType::class, [
                'label' => 'create_client_form.client_psp.label',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'help' => 'create_client_form.client_psp.help',
                'row_attr' => ['class' => 'form-element-wrapper'],
                'required' => false,
            ])
            ->add('ean', TextType::class, [
                'label' => 'create_client_form.client_ean.label',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'help' => 'create_client_form.client_ean.help',
                'row_attr' => ['class' => 'form-element-wrapper'],
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Client::class,
        ]);
    }
}
