<?php

namespace App\Controller\CreateProject;

use App\Form\CreateProject\CreateProjectForm;
use App\Service\ProjectTracker\ApiServiceInterface;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class CreateProjectController.
 */
class CreateProjectController extends AbstractController
{
    private $formData;

    public function __construct(
        private readonly ApiServiceInterface $apiService
    ) {
    }

    /**
     * Create a project and all related entities.
     *
     * @throws JsonException
     */
    #[Route('/new', name: 'create_project_form')]
    public function createProject(Request $request): Response
    {
        $form = $this->createForm(CreateProjectForm::class);
        $form->handleRequest($request);

        // Set form data.
        $this->formData = [
            'form' => $form->getData(),
            'projects' => $this->apiService->getAllProjects(),
            'accounts' => $this->apiService->getAllAccounts(),
            'projectCategories' => $this->apiService->getAllProjectCategories(),
            'allTeamsConfig' => $this->getParameter('teamconfig'),
        ];



        // Handle form submission.
        if ($form->isSubmitted() && $form->isValid()) {
            // Do stuff on submission.

            // Set selected team config.
            foreach ($this->formData['projectCategories'] as $team) {
                if ($team->id === $this->formData['form']['team']) {
                    $this->formData['selectedTeamConfig'] = $this->formData['allTeamsConfig'][$team->name];
                }
            }

            // Create project.
            $newProjectKey = $this->apiService->createJiraProject($this->formData);
            if (!$newProjectKey) {
                exit('Error: No project was created.');
            } else {
                $project = $this->apiService->getProject($newProjectKey);
            }

            // Check for new account.
            if ($this->formData['form']['new_account']) {
                // Add project ID if account key is municipality name.
                if (!is_numeric($this->formData['form']['new_account_key'])) {
                    $this->formData['form']['new_account_key'] = str_replace(
                            ' ',
                            '_',
                            $this->formData['form']['new_account_key']
                        ).'-'.$newProjectKey;
                }

                // Check for new customer.
                if ($this->formData['form']['new_customer']) {
                    // Create customer.
                    $customer = $this->createJiraCustomer();

                    // Set customer key from new customer.
                    $this->formData['form']['new_account_customer'] = $customer->key;
                } else {
                    foreach ($this->formData['accounts'] as $account) {
                        // Get the account that was selected in form.
                        // This holds the customer data we need.
                        if ($account->key === $this->formData['form']['account']) {
                            // Set customer key from selected customer.
                            $this->formData['form']['new_account_customer'] = $account->customer->key;
                        }
                    }
                }

                // Create account if new account is selected.
                $account = $this->createJiraAccount();
            } else {
                // Set account key from selected account in form.
                $account = ''; //$this->apiService->get('rest/tempo-accounts/1/account/key/'.$this->formData['form']['account']);
            }

            // Add project to tempo account
            $this->addProjectToAccount($project, $account);

            // Create project board
            $this->createProjectBoard($project);

            // Go to form submitted page.
            $_SESSION['form_data'] = $this->formData;

            return $this->redirectToRoute('create_project_submitted');
        }

        // The initial form build.
        return $this->render(
            'views/createProjectForm.html.twig',
            [
                'form' => $form->createView(),
                'formConfig' => json_encode(
                    [
                        'allProjects' => $this->allProjectsByKey(),
                    ]
                )
            ]
        );
    }

//    /**
//     * Receipt page displayed when a project was created.
//     *
//     * @Route("/submitted", name="create_project_submitted")
//     */
//    public function createProjectSubmitted(
//        MenuService $menuService,
//        HammerService $hammerService,
//        Request $request
//    ) {
//        return $this->render(
//            '@CreateProjectBundle/createProjectSubmitted.html.twig',
//            [
//                'form_data' => $_SESSION['form_data'],
//                'global_menu_items' => $menuService->getGlobalMenuItems(),
//            ]
//        );
//    }
//
//    /**
//     * Create a jira customer.
//     *
//     * @return mixed
//     *               The customer that was created or an error
//     *
//     * @TODO: Move to service that extends AbstractJiraService.
//     */
//    private function createJiraCustomer()
//    {
//        $customer = [
//            'isNew' => 1,
//            'name' => $this->formData['form']['new_customer_name'],
//            'key' => $this->formData['form']['new_customer_key'],
//        ];
//        $response = $this->hammerService->post(
//            'rest/tempo-accounts/1/customer/',
//            $customer
//        );
//
//        return $response;
//    }
//
//    /**
//     * Create a Jira account.
//     *
//     * @return mixed
//     *               The account that was created or an error
//     *
//     * @TODO: Move to service that extends AbstractJiraService.
//     */
//    private function createJiraAccount()
//    {
//        // Note! Price tables (rateTable) do not seem to work with the api at the moment. 23.05.2019
//        // Even though they are included @ http://developer.tempo.io/doc/accounts/api/rest/latest/
//        $account = [
//            'name' => $this->formData['form']['new_account_name'],
//            'key' => $this->formData['form']['new_account_key'],
//            'status' => 'OPEN',
//            'category' => [
//                'key' => 'DRIFT',
//            ],
//            'customer' => [
//                'key' => $this->formData['form']['new_account_customer'],
//            ],
//            'contact' => [
//                'username' => $this->formData['form']['new_account_contact'],
//            ],
//            'lead' => [
//                'username' => $_ENV['CPB_ACCOUNT_MANAGER'],
//            ],
//        ];
//        $response = $this->hammerService->post(
//            'rest/tempo-accounts/1/account/',
//            $account
//        );
//
//        return $response;
//    }
//

//    /**
//     * Create a project link to account.
//     *
//     * @param $project
//     *  The project that was created on form submit
//     * @param $account
//     *  The account that was created on form submit
//     *
//     * @TODO: Move to service that extends AbstractJiraService.
//     */
//    private function addProjectToAccount($project, $account)
//    {
//        $link = [
//            'scopeType' => 'PROJECT',
//            'defaultAccount' => 'true',
//            'linkType' => 'MANUAL',
//            'key' => $project->key,
//            'accountId' => $account->id,
//            'scope' => $project->id,
//        ];
//        $response = $this->hammerService->post(
//            'rest/tempo-accounts/1/link/',
//            $link
//        );
//    }
//
//    /**
//     * Create a project board and an issue filter.
//     *
//     * @param $project
//     *   The project that was created on form submit
//     *
//     * @TODO: Move to service that extends AbstractJiraService.
//     */
//    private function createProjectBoard($project)
//    {
//        // If no template is configured dont create a board.
//        if (empty($this->formData['selectedTeamConfig']['board_template'])) {
//            return;
//        }
//
//        // Create project filter.
//        $filter = [
//            'name' => 'Filter for Project: '.$project->name,
//            'description' => 'Project filter for '.$project->name,
//            'jql' => 'project = '.$project->key.' ORDER BY Rank ASC',
//            'favourite' => false,
//            'editable' => false,
//        ];
//
//        $filterResponse = $this->hammerService->post(
//            '/rest/api/2/filter',
//            $filter
//        );
//
//        // Share project filter with project members.
//        $projectShare = [
//            'type' => 'project',
//            'projectId' => $project->id,
//            'view' => true,
//            'edit' => false,
//        ];
//
//        $projectShareResponse = $this->hammerService->post(
//            '/rest/api/2/filter/'.$filterResponse->id.'/permission',
//            $projectShare
//        );
//
//        // Create board with project filter.
//        $board = [
//            'name' => 'Project: '.$project->name,
//            'type' => $this->formData['selectedTeamConfig']['board_template']['type'],
//            'filterId' => $filterResponse->id,
//        ];
//
//        $boardResponse = $this->hammerService->post(
//            '/rest/agile/1.0/board',
//            $board
//        );
//    }
//
    /**
     * Create array of all project names and their keys.
     *
     * @return array
     *   All projects indexed by key
     *
     * @throws JsonException
     */
    private function allProjectsByKey(): array
    {
        $projects = [];
        $allProjects = $this->apiService->getAllProjects();
        foreach ($allProjects as $project) {
            $projects[$project->key] = [
                'key' => $project->key,
                'name' => $project->name,
            ];
        }

        return $projects;
    }
}
