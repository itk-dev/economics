<?php

namespace App\Form;

use App\Entity\Account;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AccountType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'create_account_form.account_name_label',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'help' => 'create_account_form.account_name_help',
                'required' => true,
                'row_attr' => ['class' => 'form-element-wrapper'],
            ])
            ->add('value', TextType::class, [
                'label' => 'create_account_form.account_value_label',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'help' => 'create_account_form.account_value_help',
                'required' => true,
                'row_attr' => ['class' => 'form-element-wrapper'],
            ])
            ->add('status', TextType::class, [
                'label' => 'create_account_form.account_status_label',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'help' => 'create_account_form.account_status_help',
                'required' => true,
                'row_attr' => ['class' => 'form-element-wrapper'],
            ])
            ->add('category', TextType::class, [
                'label' => 'create_account_form.account_category_label',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'help' => 'create_account_form.account_category_help',
                'required' => true,
                'row_attr' => ['class' => 'form-element-wrapper'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Account::class,
        ]);
    }
}
