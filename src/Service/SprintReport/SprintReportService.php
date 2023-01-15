<?php

namespace App\Service\SprintReport;

use App\Entity\SprintReport\Budget;
use App\Repository\BudgetRepository;
use Doctrine\ORM\EntityManagerInterface;

class SprintReportService
{
    public function __construct(
        private readonly BudgetRepository $budgetRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function saveBudget($projectId, $versionId, $budgetAmount): Budget
    {
        $budget = $this->budgetRepository->findOneBy(['projectId' => $projectId, 'versionId' => $versionId]);

        if (!$budget) {
            $budget = new Budget();
            $budget->setProjectId($projectId);
            $budget->setVersionId($versionId);

            $this->entityManager->persist($budget);
        }

        $budget->setBudget($budgetAmount);
        $this->entityManager->flush();

        return $budget;
    }

    public function getBudget($projectId, $versionId): ?Budget
    {
        return $this->budgetRepository->findOneBy(['projectId' => $projectId, 'versionId' => $versionId]);
    }
}
