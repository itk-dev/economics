<?php

namespace App\Form\CreateProject;

use App\Service\ProjectTracker\JiraApiService;
use JsonException;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Regex;

class CreateProjectForm extends AbstractType
{
    public function __construct(
        private readonly JiraApiService $apiService,
    ) {
//        $resolver = new OptionsResolver();
//        $this->configureOptions($resolver);
    }

    /**
     * Build the form.
     *
     * @param FormBuilderInterface $builder
     *   The form builder
     * @param array $options
     *   Options related to the form
     *
     * @throws JsonException
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('project_name', TextType::class, [
            'label' => 'create_project_form.project_name.label',
            'constraints' => [
                new NotNull(['groups' => 'base']),
            ],
            'attr' => ['class' => 'form-control'],
            'help_attr' => ['class' => 'form-text text-muted'],
            'help' => 'create_project_form.project_name.help',
            'required' => false,
        ])
        ->add('project_key', TextType::class, [
            'label' => 'create_project_form.project_key.label',
            'constraints' => [
                new NotNull(['groups' => 'base']),
                new Regex([
                    'pattern' => '/^[a-zA-Z]+$/',
                    'message' => 'create_project_form.project_key.constraint.regex',
                ]),
                new Length([
                    'min' => 2,
                    'minMessage' => 'create_project_form.project_key.constraint.min',
                    'max' => 7,
                    'maxMessage' => 'create_project_form.project_key.constraint.max',
                ]),
            ],
            'attr' => ['class' => 'form-control'],
            'help_attr' => ['class' => 'form-text text-muted'],
            'help' => 'create_project_form.project_key.help',
            'required' => false,
        ])
        ->add('description', TextareaType::class, [
            'label' => 'create_project_form.description.label',
            'constraints' => [
                new NotNull(['groups' => 'base']),
            ],
            'attr' => ['class' => 'form-control', 'required'],
            'help_attr' => ['class' => 'form-text text-muted'],
            'help' => 'create_project_form.description.help',
            'required' => false,
        ])
        ->add('team', ChoiceType::class, [
            'label' => 'create_project_form.team.label',
            'choices' => $this->getTeamChoices(),
            'choice_translation_domain' => false,
            'constraints' => [
                new NotNull([
                    'message' => 'create_project_form.team.constraint.not_blank',
                    'groups' => 'base',
                ]),
            ],
            'attr' => ['class' => 'form-control'],
            'help_attr' => ['class' => 'form-text text-muted'],
            'help' => 'create_project_form.team.help',
        ]);

//        if (\in_array('ADMINISTER', $options['user_permissions'])) {
            $builder->add('account', ChoiceType::class, [
                'label' => 'create_project_form.account.label',
                'choices' => $this->getAccountChoices(),
                'choice_translation_domain' => false,
                'attr' => ['class' => 'form-control js-select2'],
                'help_attr' => ['class' => 'form-text text-muted'],
                'help' => 'create_project_form.account.help',
                'constraints' => [
                    new NotNull([
                        'groups' => 'select_account',
                        'message' => 'create_project_form.account.constraint.not_null',
                    ]),
                ],
            ])
            ->add('new_account', CheckboxType::class, [
                'label' => 'create_project_form.create_new_account.label',
                'label_attr' => ['class' => 'form-check-label'],
                'attr' => [
                    'class' => 'form-check-input',
                    'data-toggle' => 'collapse',
                    'data-target' => '.toggle-account-group',
                ],
                'help_attr' => ['class' => 'form-text text-muted'],
                'help' => 'create_project_form.create_new_account.help',
                'required' => false,
                'validation_groups' => ['account'],
            ])
            ->add('new_account_name', TextType::class, [
                'label' => 'create_project_form.new_account_name.label',
                'attr' => ['class' => 'form-control'],
                'required' => false,
                'help_attr' => ['class' => 'form-text text-muted'],
                'help' => 'create_project_form.new_account_name.help',
                'constraints' => [
                    new NotNull(['groups' => 'account']),
                ],
            ])
            ->add('new_account_key', TextType::class, [
                'label' => 'create_project_form.new_account_key.label',
                'attr' => ['class' => 'form-control'],
                'required' => false,
                'help_attr' => ['class' => 'form-text text-muted'],
                'help' => 'create_project_form.new_account_key.help',
                'constraints' => [
                    new NotNull(['groups' => 'account']),
                ],
            ])
            ->add('new_account_contact', TextType::class, [
                'label' => 'create_project_form.new_account_contact.label',
                'attr' => ['class' => 'form-control'],
                'required' => false,
                'help_attr' => ['class' => 'form-text text-muted'],
                'help' => 'create_project_form.new_account_contact.help',
                'constraints' => [
                    new NotNull(['groups' => 'account']),
                ],
            ])
            ->add('new_account_customer', ChoiceType::class, [
                'label' => 'create_project_form.new_account_customer.label',
                'choices' => $this->getCustomerChoices(),
                'choice_translation_domain' => false,
                'required' => false,
                'attr' => ['class' => 'form-control js-select2'],
                'help_attr' => ['class' => 'form-text text-muted'],
                'help' => 'create_project_form.new_account_customer.help',
                'constraints' => [
                    new NotNull([
                        'groups' => 'select_customer',
                        'message' => 'create_project_form.new_account_customer.constraint.not_null',
                    ]),
                ],
            ])
            ->add('new_customer', CheckboxType::class, [
                'label' => 'create_project_form.new_customer.label',
                'label_attr' => ['class' => 'form-check-label'],
                'attr' => [
                    'class' => 'form-check-input',
                    'data-toggle' => 'collapse',
                    'data-target' => '.toggle-customer-group',
                ],
                'required' => false,
                'help_attr' => ['class' => 'form-text text-muted'],
                'help' => 'create_project_form.new_customer.help',
                'validation_groups' => ['customer'],
            ])
            ->add('new_customer_name', TextType::class, [
                'label' => 'create_project_form.new_customer_name.label',
                'attr' => ['class' => 'form-control'],
                'required' => false,
                'help_attr' => ['class' => 'form-text text-muted'],
                'help' => 'create_project_form.new_customer_name.help',
                'constraints' => [
                    new NotNull(['groups' => 'customer']),
                ],
            ])
            ->add('new_customer_key', TextType::class, [
                'label' => 'create_project_form.new_customer_key.label',
                'attr' => ['class' => 'form-control'],
                'required' => false,
                'help_attr' => ['class' => 'form-text text-muted'],
                'help' => 'create_project_form.new_customer_key.help',
                'constraints' => [
                    new NotNull(['groups' => 'customer']),
                    new Regex([
                        'pattern' => '/^([0-9]{4}|[0-9]{13})$/',
                        'message' => 'create_project_form.new_customer_key.constraint.regex',
                        'groups' => 'customer',
                    ]),
                ],
            ]);
//        }
        $builder->add('save', SubmitType::class, [
            'label' => 'create_project_form.save.label',
            'attr' => ['class' => 'btn btn-primary'],
        ]);
    }

    /**
     * Generate an array of teams from project categories.
     *
     * @return array
     *   A list of teams and their id
     *
     * @throws JsonException
     */
    private function getTeamChoices(): array
    {
        $projectCategories = $this->apiService->getAllProjectCategories();
        $teams = [];
        foreach ($projectCategories as $team) {
            $teams[$team->name] = $team->id;
        }

        return ['-- Select --' => null] + $teams;
    }

    /**
     * Generate an array of accounts from tempo accounts.
     *
     * @return array
     *   A list of tempo accounts and their key
     *
     * @throws JsonException
     */
    private function getAccountChoices(): array
    {
        $accounts = $this->apiService->getAllAccounts();
        $optionalAccounts = [];
        foreach ($accounts as $account) {
            $optionalAccounts[$account->name.' ('.$account->key.')'] = $account->key;
        }
        return ['-- Select --' => null] + $optionalAccounts;
    }

    /**
     * Generate an array of customers from tempo customers.
     *
     * @return array
     *   A list of tempo customers and their key
     *
     * @throws JsonException
     */
    private function getCustomerChoices(): array
    {
        $customers = $this->apiService->getAllCustomers();
        $optionalCustomerChoices = [];
        foreach ($customers as $customer) {
            $optionalCustomerChoices[$customer->name.' ('.$customer->key.')'] = $customer->key;
        }
        return ['-- Select --' => null] + $optionalCustomerChoices;
    }

//    /**
//     * Perform validation in groups based on choices during submit.
//     *
//     * @param OptionsResolver $resolver Options related to form
//     */
//    public function configureOptions(OptionsResolver $resolver)
//    {
//        $resolver->setDefaults([
//            'user_permissions' => null,
//            'validation_groups' => function (FormInterface $form) {
//                $data = $form->getData();
//                if (true === $data['new_account']) {
//                    if (true === $data['new_customer']) {
//                        return ['Default', 'base', 'account', 'customer'];
//                    }
//
//                    return ['Default', 'base', 'account', 'select_customer'];
//                }
//
//                return ['Default', 'base', 'select_account'];
//            },
//        ]);
//    }
}
