<?php

namespace App\Controller\Planning;

use App\Service\ProjectTracker\ApiServiceInterface;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PlanningController extends AbstractController
{
    public function __construct(
        private readonly ApiServiceInterface $apiService
    ) {
    }

    /**
     * @throws \Exception
     */
    #[Route('/planning', name: 'app_planning')]
    public function index(): Response
    {
        $sprints = [];
        $sprintIssues = [];
        $sprintCells = [];

        $boardId = 30;
        $now = new DateTime();

        $allSprints = $this->apiService->getAllSprints($boardId);

        $weekGoalLow = 20;
        $weekGoalHigh = 30;

        foreach ($allSprints as $sprint) {
            if (isset($sprint->endDate)) {
                $endDateString = $sprint->endDate;

                $endDate = new \DateTime($endDateString);

                if ($endDate < $now) {
                    //continue;
                }

                // Expected sprint name examples.
                // $a = "DEV sprint uge 2-3-4.23";
                // $b = "ServiceSupport uge 5.23";

                $pattern = "/(?<weeks>(?:-?\d+-?)*)\.(?<year>\d+)$/";

                $matches = [];

                preg_match_all($pattern, $sprint->name, $matches);

                if (!empty($matches['weeks'])) {
                    $weeks = count(explode('-', $matches['weeks'][0]));
                } else {
                    $weeks = 1;
                }

                $sprints[] = [
                    'id' => $sprint->id,
                    'weeks' => $weeks,
                    'sprintGoalLow' => $weekGoalLow * $weeks,
                    'sprintGoalHigh' => $weekGoalHigh * $weeks,
                    'name' => $sprint->name,
                ];

                $issues = $this->apiService->getIssuesInSprint($boardId, $sprint->id);

                $sprintIssues[$sprint->id] = $issues;
            }
        }

        $assignees = [
            'unassigned' => (object) [
                'key' => 'unassigned',
                'displayName' => 'Unassigned',
            ],
        ];

        foreach ($sprintIssues as $sprintId => $issues) {
            foreach ($issues as $issue) {
                if ($issue->fields->status->statusCategory->key !== 'done') {
                    $assignee = $issue->fields->assignee ?? null;

                    if ($assignee == null) {
                        $assigneeKey = 'unassigned';
                    }
                    else {
                        $assigneeKey = $assignee->key;
                    }

                    if (!array_key_exists($assigneeKey, $assignees)) {
                        $assignees[$assigneeKey] = $assignee;
                    }

                    if (!isset($sprintCells[$assigneeKey][$sprintId])) {
                        $sprintCells[$assigneeKey][$sprintId] = 0;
                    }

                    $sprintCells[$assigneeKey][$sprintId] = $sprintCells[$assigneeKey][$sprintId] + ($issue->fields->timetracking->remainingEstimateSeconds ?? 0);
                }
            }
        }

        usort($assignees, function ($a, $b) {
            return mb_strtolower($a->displayName) > mb_strtolower($b->displayName);
        });

        return $this->render('planning/index.html.twig', [
            'controller_name' => 'PlanningController',
            'sprints' => $sprints,
            'assignees' => $assignees,
            'sprintCells' => $sprintCells,
        ]);
    }
}
