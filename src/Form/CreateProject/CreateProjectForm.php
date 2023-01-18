<?php

namespace App\Form\CreateProject;

use App\Exception\ApiServiceException;
use App\Service\ProjectTracker\JiraApiService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Regex;

class CreateProjectForm extends AbstractType
{
    public function __construct(
        private readonly JiraApiService $apiService,
    ) {
    }

    /**
     * Build the form.
     *
     * @param FormBuilderInterface $builder
     *                                      The form builder
     * @param array                $options
     *                                      Options related to the form
     *
     * @throws ApiServiceException
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('project_name', TextType::class, [
            'label' => 'create_project_form.project_name.label',
            'label_attr' => [
                'class' => 'form-label',
            ],
            'constraints' => [
                new NotNull(['groups' => 'base']),
            ],
            'attr' => [
                'class' => 'form-element',
            ],
            'help_attr' => [
                'class' => 'form-help',
            ],
            'help' => 'create_project_form.project_name.help',
            'required' => false,
            'row_attr' => ['class' => 'form-element-wrapper'],
        ])
            ->add('project_key', TextType::class, [
                'label' => 'create_project_form.project_key.label',
                'label_attr' => [
                    'class' => 'form-label',
                ],
                'constraints' => [
                    new NotNull(['groups' => 'base']),
                    new Regex([
                        'pattern' => '/^[a-zA-Z0-9]+$/',
                        'message' => 'create_project_form.project_key.constraint.regex',
                    ]),
                    new Length([
                        'min' => 2,
                        'minMessage' => 'create_project_form.project_key.constraint.min',
                        'max' => 8,
                        'maxMessage' => 'create_project_form.project_key.constraint.max',
                    ]),
                ],
                'attr' => [
                    'class' => 'form-element',
                ],
                'help_attr' => [
                    'class' => 'form-help',
                ],
                'help' => 'create_project_form.project_key.help',
                'required' => false,
                'row_attr' => ['class' => 'form-element-wrapper'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'create_project_form.description.label',
                'label_attr' => [
                    'class' => 'form-label',
                ],
                'constraints' => [
                    new NotNull(['groups' => 'base']),
                ],
                'attr' => [
                    'class' => 'form-element',
                    'rows' => 5,
                ],
                'help_attr' => [
                    'class' => 'form-help',
                ],
                'help' => 'create_project_form.description.help',
                'required' => false,
                'row_attr' => ['class' => 'form-element-wrapper'],
            ])
            ->add('team', ChoiceType::class, [
                'label' => 'create_project_form.team.label',
                'label_attr' => [
                    'class' => 'form-label',
                ],
                'choices' => $this->getTeamChoices(),
                'choice_translation_domain' => false,
                'constraints' => [
                    new NotNull([
                        'message' => 'create_project_form.team.constraint.not_blank',
                        'groups' => 'base',
                    ]),
                ],
                'attr' => [
                    'class' => 'form-element',
                ],
                'help_attr' => [
                    'class' => 'form-help',
                ],
                'help' => 'create_project_form.team.help',
                'row_attr' => ['class' => 'form-element-wrapper'],
            ])
            ->add('account', ChoiceType::class, [
                'label' => 'create_project_form.account.label',
                'label_attr' => [
                    'class' => 'form-label',
                ],
                'choices' => $this->getAccountChoices(),
                'choice_translation_domain' => false,
                'attr' => ['class' => 'form-element js-select2'],
                'help_attr' => [
                    'class' => 'form-help',
                ],
                'help' => 'create_project_form.account.help',
                'constraints' => [
                    new NotNull([
                        'groups' => 'select_account',
                        'message' => 'create_project_form.account.constraint.not_null',
                    ]),
                ],
                'row_attr' => ['class' => 'form-element-wrapper'],
            ])
            ->add('new_account', CheckboxType::class, [
                'label' => 'create_project_form.create_new_account.label',
                'label_attr' => ['class' => 'form-label'],
                'attr' => [
                    'class' => 'form-check-input',
                    'data-toggle' => 'collapse',
                    'data-target' => '.toggle-account-group',
                ],
                'help_attr' => [
                    'class' => 'form-help',
                ],
                'help' => 'create_project_form.create_new_account.help',
                'required' => false,
                'validation_groups' => ['account'],
                'row_attr' => ['class' => 'form-element-wrapper'],
            ])
            ->add('new_account_name', TextType::class, [
                'label' => 'create_project_form.new_account_name.label',
                'label_attr' => [
                    'class' => 'form-label',
                ],
                'attr' => [
                    'class' => 'form-element',
                ],
                'required' => false,
                'help_attr' => [
                    'class' => 'form-help',
                ],
                'help' => 'create_project_form.new_account_name.help',
                'constraints' => [
                    new NotNull(['groups' => 'account']),
                ],
                'row_attr' => ['class' => 'form-element-wrapper form-group-account hidden'],
            ])
            ->add('new_account_key', TextType::class, [
                'label' => 'create_project_form.new_account_key.label',
                'label_attr' => [
                    'class' => 'form-label',
                ],
                'attr' => [
                    'class' => 'form-element form-group-account',
                ],
                'required' => false,
                'help_attr' => [
                    'class' => 'form-help',
                ],
                'help' => 'create_project_form.new_account_key.help',
                'constraints' => [
                    new NotNull(['groups' => 'account']),
                ],
                'row_attr' => ['class' => 'form-element-wrapper form-group-account hidden'],
            ])
            ->add('new_account_contact', TextType::class, [
                'label' => 'create_project_form.new_account_contact.label',
                'label_attr' => [
                    'class' => 'form-label',
                ],
                'attr' => [
                    'class' => 'form-element',
                ],
                'required' => false,
                'help_attr' => [
                    'class' => 'form-help form-group-account',
                ],
                'help' => 'create_project_form.new_account_contact.help',
                'constraints' => [
                    new NotNull(['groups' => 'account']),
                ],
                'row_attr' => ['class' => 'form-element-wrapper form-group-account hidden'],
            ])
            ->add('new_account_customer', ChoiceType::class, [
                'label' => 'create_project_form.new_account_customer.label',
                'label_attr' => [
                    'class' => 'form-label',
                ],
                'choices' => $this->getCustomerChoices(),
                'choice_translation_domain' => false,
                'required' => false,
                'attr' => ['class' => 'form-element js-select2'],
                'help_attr' => [
                    'class' => 'form-help',
                ],
                'help' => 'create_project_form.new_account_customer.help',
                'constraints' => [
                    new NotNull([
                        'groups' => 'select_customer',
                        'message' => 'create_project_form.new_account_customer.constraint.not_null',
                    ]),
                ],
                'row_attr' => ['class' => 'form-element-wrapper form-group-account hidden'],
            ])
            ->add('new_customer', CheckboxType::class, [
                'label' => 'create_project_form.new_customer.label',
                'label_attr' => [
                    'class' => 'form-label',
                ],
                'attr' => [
                    'class' => 'form-check-input',
                    'data-toggle' => 'collapse',
                    'data-target' => '.toggle-customer-group',
                ],
                'required' => false,
                'help_attr' => [
                    'class' => 'form-help',
                ],
                'help' => 'create_project_form.new_customer.help',
                'validation_groups' => ['customer'],
                'row_attr' => ['class' => 'form-element-wrapper hidden'],
            ])
            ->add('new_customer_name', TextType::class, [
                'label' => 'create_project_form.new_customer_name.label',
                'attr' => [
                    'class' => 'form-element',
                ],
                'required' => false,
                'help_attr' => [
                    'class' => 'form-help',
                ],
                'help' => 'create_project_form.new_customer_name.help',
                'constraints' => [
                    new NotNull(['groups' => 'customer']),
                ],
                'row_attr' => ['class' => 'form-element-wrapper form-group-customer hidden'],
            ])
            ->add('new_customer_key', TextType::class, [
                'label' => 'create_project_form.new_customer_key.label',
                'label_attr' => [
                    'class' => 'form-label',
                ],
                'attr' => [
                    'class' => 'form-element',
                ],
                'required' => false,
                'help_attr' => [
                    'class' => 'form-help',
                ],
                'help' => 'create_project_form.new_customer_key.help',
                'constraints' => [
                    new NotNull(['groups' => 'customer']),
                    new Regex([
                        'pattern' => '/^([0-9]{4}|[0-9]{13})$/',
                        'message' => 'create_project_form.new_customer_key.constraint.regex',
                        'groups' => 'customer',
                    ]),
                ],
                'row_attr' => ['class' => 'form-element-wrapper form-group-customer hidden'],
            ]);

        $builder->add('save', SubmitType::class, [
            'label' => 'create_project_form.save.label',
            'attr' => ['class' => 'btn-primary'],
        ]);
    }

    /**
     * Generate an array of teams from project categories.
     *
     * @return array
     *               A list of teams and their id
     *
     * @throws ApiServiceException
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
     *               A list of tempo accounts and their key
     *
     * @throws ApiServiceException
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
     *               A list of tempo customers and their key
     *
     * @throws ApiServiceException
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
}
