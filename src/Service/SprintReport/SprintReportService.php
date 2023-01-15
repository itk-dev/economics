<?php

namespace App\Service\SprintReport;

use App\Entity\SprintReport\SprintReportBudget;
use App\Repository\SprintReportBudgetRepository;
use Doctrine\ORM\EntityManagerInterface;

class SprintReportService
{
    public function __construct(
        private readonly SprintReportBudgetRepository $budgetRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function saveBudget($projectId, $versionId, $budgetAmount): SprintReportBudget
    {
        $budget = $this->budgetRepository->findOneBy(['projectId' => $projectId, 'versionId' => $versionId]);

        if (!$budget) {
            $budget = new SprintReportBudget();
            $budget->setProjectId($projectId);
            $budget->setVersionId($versionId);

            $this->entityManager->persist($budget);
        }

        $budget->setBudget($budgetAmount);
        $this->entityManager->flush();

        return $budget;
    }

    public function getBudget($projectId, $versionId): ?SprintReportBudget
    {
        return $this->budgetRepository->findOneBy(['projectId' => $projectId, 'versionId' => $versionId]);
    }
}
