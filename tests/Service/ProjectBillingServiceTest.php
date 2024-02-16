<?php

namespace App\Tests\Service;

use App\Entity\ProjectBilling;
use App\Repository\IssueRepository;
use App\Repository\ProjectRepository;
use App\Service\BillingService;
use App\Service\ProjectBillingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ProjectBillingServiceTest extends KernelTestCase
{
    public function testGetIssuesNotIncludedInProjectBilling(): void
    {
        self::bootKernel();

        $container = self::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);

        /** @var ProjectRepository $projectRepository */
        $projectRepository = $container->get(ProjectRepository::class);

        /** @var ProjectBillingService $projectBillingService */
        $projectBillingService = $container->get(ProjectBillingService::class);

        /** @var BillingService $projectBillingService */
        $billingService = $container->get(BillingService::class);

        $project = $projectRepository->findOneBy([], ['id' => 'asc']);

        $projectBilling = new ProjectBilling();
        $projectBilling->setPeriodStart((new \DateTime())->sub(new \DateInterval('P1D')));
        $projectBilling->setPeriodEnd((new \DateTime())->add(new \DateInterval('P1D')));
        $projectBilling->setName('Project Billing 1');
        $projectBilling->setProject($project);
        $projectBilling->setRecorded(false);
        $projectBilling->setDescription('Project billing');

        $issues = $container->get(IssueRepository::class)->getClosedIssuesFromInterval($projectBilling->getProject(), $projectBilling->getPeriodStart(), $projectBilling->getPeriodEnd());
        $this->assertCount(10, $issues);

        $entityManager->persist($projectBilling);
        $entityManager->flush();

        $projectBillingService->createProjectBilling($projectBilling->getId());

        $this->assertCount(2, $projectBilling->getInvoices());

        $issues = $projectBillingService->getIssuesNotIncludedInProjectBilling($projectBilling);

        $this->assertCount(4, $issues);

        $ids = $projectBilling->getInvoices()->map(fn ($invoice) => $invoice->getId())->toArray();

        $spreadsheet = $billingService->exportInvoicesToSpreadsheet($ids);

        $this->assertNotNull($spreadsheet);

        $spreadsheetArray = $spreadsheet->getActiveSheet()->toArray(null, false, false);

        $this->assertCount(8, $spreadsheetArray);

        // Internal
        $this->assertEquals('H', $spreadsheetArray[0][0]);
        $this->assertEquals('Customer Key 0-0', $spreadsheetArray[0][1]);
        $this->assertEquals('0020', $spreadsheetArray[0][5]);
        $this->assertEquals('10', $spreadsheetArray[0][6]);
        $this->assertEquals('20', $spreadsheetArray[0][7]);
        $this->assertEquals('ZIRA', $spreadsheetArray[0][8]);
        $this->assertEquals('Att: Kontakt Kontaktesen 0', $spreadsheetArray[0][14]);
        $this->assertEquals($projectBilling->getDescription(), $spreadsheetArray[0][15]);
        $this->assertEquals('0000001111', $spreadsheetArray[0][16]);

        $this->assertEquals('L', $spreadsheetArray[1][0]);
        $this->assertEquals('L', $spreadsheetArray[2][0]);
        $this->assertEquals('L', $spreadsheetArray[3][0]);

        // External
        $this->assertEquals('H', $spreadsheetArray[4][0]);
        $this->assertEquals('Customer Key 0-1', $spreadsheetArray[4][1]);
        $this->assertEquals('0020', $spreadsheetArray[4][5]);
        $this->assertEquals('20', $spreadsheetArray[4][6]);
        $this->assertEquals('20', $spreadsheetArray[4][7]);
        $this->assertEquals('ZRA', $spreadsheetArray[4][8]);
        $this->assertEquals('Att: Kontakt Kontaktesen 0', $spreadsheetArray[4][14]);
        $this->assertEquals($projectBilling->getDescription(), $spreadsheetArray[4][15]);
        $this->assertEquals('', $spreadsheetArray[4][16]);
        $this->assertNotEmpty($spreadsheetArray[4][23]);
        $this->assertNotEmpty($spreadsheetArray[4][24]);
        $this->assertNotEmpty($spreadsheetArray[4][25]);
        $this->assertEquals('KOCIVIL', $spreadsheetArray[4][31]);
        $this->assertNotEmpty($spreadsheetArray[4][34]);
        $this->assertNotEmpty($spreadsheetArray[4][35]);

        $this->assertEquals('L', $spreadsheetArray[5][0]);
        $this->assertEquals('L', $spreadsheetArray[6][0]);
        $this->assertEquals('L', $spreadsheetArray[7][0]);
    }
}
