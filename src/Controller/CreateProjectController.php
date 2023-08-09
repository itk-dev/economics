<?php

namespace App\Controller;

use App\Form\CreateProjectType;
use App\Service\ApiServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class CreateProjectController.
 */
#[Route('/admin/create-project')]
class CreateProjectController extends AbstractController
{
    private const SESSION_KEY = 'create_project_form_data';

    public function __construct(
        private readonly ApiServiceInterface $apiService
    ) {
    }

    /**
     * Create a project and all related entities.
     *
     * TODO: Refactor code to move Jira specifics into JiraApiService.
     */
    #[Route('/new', name: 'create_project_form')]
    public function createProject(Request $request): Response
    {
        $form = $this->createForm(CreateProjectType::class);
        $form->handleRequest($request);

        // Set form data.
        $formData = [
            'form' => $form->getData(),
            'projects' => $this->apiService->getAllProjects(),
            'accounts' => $this->apiService->getAllAccounts(),
            'projectCategories' => $this->apiService->getAllProjectCategories(),
            'allTeamsConfig' => (array) $this->getParameter('teamconfig'),
        ];

        // Handle form submission.
        if ($form->isSubmitted() && $form->isValid()) {
            // Set selected team config.
            foreach ($formData['projectCategories'] as $team) {
                if ($team->id === $formData['form']['team']) {
                    $formData['selectedTeamConfig'] = $formData['allTeamsConfig'][$team->name];
                }
            }

            // Create project.
            $newProjectKey = $this->apiService->createProject($formData);
            if (!$newProjectKey) {
                exit('Error: No project was created.');
            } else {
                $project = $this->apiService->getProject($newProjectKey);
            }

            // Check for new account.
            if ($formData['form']['new_account']) {
                // Add project ID if account key is municipality name.
                if (!is_numeric($formData['form']['new_account_key'])) {
                    /** @var string $accountKeyReplaced */
                    $accountKeyReplaced = str_replace(
                        ' ',
                        '_',
                        $formData['form']['new_account_key']
                    );
                    $formData['form']['new_account_key'] = $accountKeyReplaced.'-'.$newProjectKey;
                }

                // Check for new customer.
                if ($formData['form']['new_customer']) {
                    // Create customer.
                    $customer = $this->apiService->createTimeTrackerCustomer($formData['form']['new_customer_name'], $formData['form']['new_customer_key']);

                    // Set customer key from new customer.
                    $formData['form']['new_account_customer'] = $customer->key;
                } else {
                    foreach ($formData['accounts'] as $account) {
                        // Get the account that was selected in form.
                        // This holds the customer data we need.
                        if ($account->key === $formData['form']['account']) {
                            // Set customer key from selected customer.
                            $formData['form']['new_account_customer'] = $account->customer->key;
                        }
                    }
                }

                // Create account if new account is selected.
                $account = $this->apiService->createTimeTrackerAccount(
                    $formData['form']['new_account_name'],
                    $formData['form']['new_account_key'],
                    $formData['form']['new_account_customer'],
                    $formData['form']['new_account_contact']
                );
            } else {
                // Set account key from selected account in form.
                $account = $this->apiService->getTimeTrackerAccount($formData['form']['account']);
            }

            // Add project to tempo account
            $this->apiService->addProjectToTimeTrackerAccount($project, $account);

            // Create project board
            if (!empty($formData['selectedTeamConfig']) && !empty($formData['selectedTeamConfig']['board_template'])) {
                $this->apiService->createProjectBoard($formData['selectedTeamConfig']['board_template']['type'], $project);
            }

            $session = $request->getSession();
            $session->set(self::SESSION_KEY, $formData);

            return $this->redirectToRoute('create_project_submitted');
        }

        // The initial form build.
        return $this->render(
            'create_project/form.html.twig',
            [
                'form' => $form->createView(),
                'formConfig' => json_encode(
                    [
                        'allProjects' => $this->allProjectsByKey(),
                    ]
                ),
            ]
        );
    }

    /**
     * Receipt page displayed when a project was created.
     *
     * TODO: Refactor code to move Jira specifics into JiraApiService.
     *
     * @Route("/submitted", name="create_project_submitted")
     */
    public function submitted(Request $request): Response
    {
        // Get session data, and clean session entry.
        $session = $request->getSession();
        $formData = $session->get(self::SESSION_KEY);
        $session->remove(self::SESSION_KEY);

        $endpoints = $this->apiService->getEndpoints();
        $url = $endpoints['base'];
        $url = is_string($url) ? $url : '';
        $teamId = $formData['selectedTeamConfig']['tempo_team_id'] ?? '';

        return $this->render(
            'create_project/submitted.html.twig',
            [
                'url' => "$url/secure/Tempo.jspa#/teams/team/$teamId/" ?? null,
                'projectName' => $formData['form']['project_name'] ?? null,
                'projectKey' => $formData['form']['project_key'] ?? null,
                'description' => $formData['form']['description'] ?? null,
                'teamName' => $formData['form']['selectedTeamConfig']['team_name'] ?? null,
                'account' => $formData['form']['account'] ?? null,
                'newAccount' => $formData['form']['new_account'] ?? false,
                'newAccountName' => $formData['form']['new_account_name'] ?? null,
                'newAccountKey' => $formData['form']['new_account_key'] ?? null,
                'newAccountContact' => $formData['form']['new_account_contact'] ?? null,
                'newAccountCustomer' => $formData['form']['new_account_customer'] ?? null,
                'newCustomer' => $formData['form']['new_customer'] ?? false,
                'newCustomerName' => $formData['form']['new_customer_name'] ?? null,
                'newCustomerKey' => $formData['form']['new_customer_key'] ?? null,
            ]
        );
    }

    /**
     * Create array of all project names and their keys.
     *
     * @return array All projects indexed by key
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
