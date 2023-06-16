<?php

namespace App\Service;

use App\Entity\ProjectVersionBudget;
use App\Repository\ProjectVersionBudgetRepository;
use Doctrine\ORM\EntityManagerInterface;

class SprintReportService
{
    public function __construct(
        private readonly ProjectVersionBudgetRepository $budgetRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function saveBudget($projectId, $versionId, $budgetAmount): ProjectVersionBudget
    {
        $budget = $this->budgetRepository->findOneBy(['projectId' => $projectId, 'versionId' => $versionId]);

        if (!$budget) {
            $budget = new ProjectVersionBudget();
            $budget->setProjectId($projectId);
            $budget->setVersionId($versionId);

            $this->entityManager->persist($budget);
        }

        $budget->setBudget($budgetAmount);

        $this->entityManager->flush();

        return $budget;
    }

    public function getBudget($projectId, $versionId): ?ProjectVersionBudget
    {
        return $this->budgetRepository->findOneBy(['projectId' => $projectId, 'versionId' => $versionId]);
    }
}
