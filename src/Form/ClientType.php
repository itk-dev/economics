<?php

namespace App\Form;

use App\Entity\Client;
use App\Repository\InvoiceRepository;
use App\Service\JiraApiService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;

class ClientType extends AbstractType
{
    public function __construct(
        private readonly JiraApiService $apiService,
        private readonly InvoiceRepository $invoiceRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'create_client_form.client_name.label',
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
                'help' => 'create_client_form.client_name.help',
                'required' => true,
                'row_attr' => ['class' => 'form-element-wrapper'],
            ])
            ->add('contact', TextType::class, [
                'label' => 'create_client_form.client_contact.label',
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
                'help' => 'create_client_form.client_contact.help',
                'required' => true,
                'row_attr' => ['class' => 'form-element-wrapper'],
            ])
            ->add('standardPrice', TextType::class, [
                'label' => 'create_client_form.client_standardPrice.label',
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
                'help' => 'create_client_form.client_standardPrice.help',
                'required' => true,
                'row_attr' => ['class' => 'form-element-wrapper'],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'create_client_form.type.label',
                'label_attr' => ['class' => 'label'],
                'choices' => $this->getTypeChoices(),
                'choice_translation_domain' => false,
                'constraints' => [
                    new NotNull([
                        'message' => 'create_client_form.type.constraint.not_blank',
                        'groups' => 'base',
                    ]),
                ],
                'attr' => [
                    'class' => 'form-element',
                    'data-create-project-target' => 'choice',
                ],
                'help_attr' => [
                    'class' => 'form-help',
                ],
                'help' => 'create_client_form.type.help',
                'required' => true,
                'row_attr' => ['class' => 'form-element-wrapper'],
            ])
            ->add('account', TextType::class, [
                'label' => 'create_client_form.client_account.label',
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
                'help' => 'create_client_form.client_account.help',
                'required' => true,
                'row_attr' => ['class' => 'form-element-wrapper'],
            ])
            ->add('psp', TextType::class, [
                'label' => 'create_client_form.client_psp.label',
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
                'help' => 'create_client_form.client_psp.help',
                'required' => true,
                'row_attr' => ['class' => 'form-element-wrapper'],
            ])
            ->add('ean', TextType::class, [
                'label' => 'create_client_form.client_ean.label',
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
                'help' => 'create_client_form.client_ean.help',
                'required' => true,
                'row_attr' => ['class' => 'form-element-wrapper'],
            ])
            ->add('projectTrackerId', TextType::class, [
                'label' => 'create_client_form.client_projectTrackerId.label',
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
                'help' => 'create_client_form.client_projectTrackerId.help',
                'required' => true,
                'row_attr' => ['class' => 'form-element-wrapper'],
            ]);
    }

    /**
     * Generate an array of client types.
     *
     * @return array
     *               A list of possible client types
     */
    private function getTypeChoices(): array
    {
        return ['-- Select --' => null];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Client::class,
        ]);
    }
}
