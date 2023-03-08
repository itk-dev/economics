<?php

namespace App\Controller\CreateProject;

use App\Form\CreateProject\CreateProjectForm;
use App\Service\ProjectTracker\ApiServiceInterface;
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
    public function __construct(
        private readonly ApiServiceInterface $apiService
    ) {
    }

    /**
     * Create a project and all related entities.
     */
    #[Route('/new', name: 'create_project_form')]
    public function createProject(Request $request): Response
    {
        $form = $this->createForm(CreateProjectForm::class);
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
            // Do stuff on submission.

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
                    $formData['form']['new_account_key'] = str_replace(
                        ' ',
                        '_',
                        $formData['form']['new_account_key']
                    ).'-'.$newProjectKey;
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
            if (!empty($formData['selectedTeamConfig']['board_template'])) {
                $this->apiService->createProjectBoard($formData['selectedTeamConfig']['board_template']['type'], $project);
            }

            // Go to form submitted page.
            $_SESSION['form_data'] = $formData;

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
     * @Route("/submitted", name="create_project_submitted")
     */
    public function submitted(Request $request): Response
    {
        return $this->render(
            'create_project/submitted.html.twig',
            [
                'form_data' => $_SESSION['form_data'],
            ]
        );
    }

    /**
     * Create array of all project names and their keys.
     *
     * @return array
     *               All projects indexed by key
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
