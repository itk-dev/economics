<?php

namespace App\Form;

use App\Entity\Account;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;

class AccountType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'create_account_form.account_name.label',
                'label_attr' => ['class' => 'label'],
                'constraints' => [
                    new NotNull(['groups' => 'base']),
                ],
                'attr' => [
                    'class' => 'form-element',
                ],
                'help_attr' => [
                    'class' => 'form-help',
                ],
                'help' => 'create_client_form.account_name.help',
                'required' => true,
                'row_attr' => ['class' => 'form-element-wrapper'],
            ])
            ->add('value', TextType::class, [
                'label' => 'create_account_form.account_value.label',
                'label_attr' => ['class' => 'label'],
                'constraints' => [
                    new NotNull(['groups' => 'base']),
                ],
                'attr' => [
                    'class' => 'form-element',
                ],
                'help_attr' => [
                    'class' => 'form-help',
                ],
                'help' => 'create_client_form.account_value.help',
                'required' => true,
                'row_attr' => ['class' => 'form-element-wrapper'],
            ])
            ->add('projectTrackerId', TextType::class, [
                'label' => 'create_account_form.account_projectTrackerId.label',
                'label_attr' => ['class' => 'label'],
                'constraints' => [
                    new NotNull(['groups' => 'base']),
                ],
                'attr' => [
                    'class' => 'form-element',
                ],
                'help_attr' => [
                    'class' => 'form-help',
                ],
                'help' => 'create_client_form.account_projectTrackerId.help',
                'required' => true,
                'row_attr' => ['class' => 'form-element-wrapper'],
            ])
            ->add('source', TextType::class, [
                'label' => 'create_account_form.account_source.label',
                'label_attr' => ['class' => 'label'],
                'constraints' => [
                    new NotNull(['groups' => 'base']),
                ],
                'attr' => [
                    'class' => 'form-element',
                ],
                'help_attr' => [
                    'class' => 'form-help',
                ],
                'help' => 'create_client_form.account_source.help',
                'required' => true,
                'row_attr' => ['class' => 'form-element-wrapper'],
            ])
            ->add('status', TextType::class, [
                'label' => 'create_account_form.account_status.label',
                'label_attr' => ['class' => 'label'],
                'constraints' => [
                    new NotNull(['groups' => 'base']),
                ],
                'attr' => [
                    'class' => 'form-element',
                ],
                'help_attr' => [
                    'class' => 'form-help',
                ],
                'help' => 'create_client_form.account_status.help',
                'required' => true,
                'row_attr' => ['class' => 'form-element-wrapper'],
            ])
            ->add('category', TextType::class, [
                'label' => 'create_account_form.account_category.label',
                'label_attr' => ['class' => 'label'],
                'constraints' => [
                    new NotNull(['groups' => 'base']),
                ],
                'attr' => [
                    'class' => 'form-element',
                ],
                'help_attr' => [
                    'class' => 'form-help',
                ],
                'help' => 'create_client_form.account_category.help',
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
