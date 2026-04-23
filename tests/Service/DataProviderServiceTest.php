<?php

namespace App\Tests\Service;

use App\Entity\DataProvider;
use App\Entity\Issue;
use App\Entity\Project;
use App\Entity\Worklog;
use App\Enum\BillableKindsEnum;
use App\Enum\IssueStatusEnum;
use App\Model\DataProvider\DataProviderIssueData;
use App\Repository\IssueRepository;
use App\Repository\WorklogRepository;
use App\Service\DataProviderService;
use App\Service\LeantimeApiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DataProviderServiceTest extends KernelTestCase
{
    public function testUpsertIssueProjectChangeMovesWorklogs(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $entityManager = $container->get(EntityManagerInterface::class);
        $worklogRepository = $container->get(WorklogRepository::class);
        $issueRepository = $container->get(IssueRepository::class);
        $service = $container->get(DataProviderService::class);

        // Create data provider.
        $dataProvider = new DataProvider();
        $dataProvider->setName('Test Provider - Worklog Move');
        $dataProvider->setEnabled(true);
        $dataProvider->setClass(LeantimeApiService::class);
        $dataProvider->setUrl('www.example.com');
        $dataProvider->setSecret('secret');
        $entityManager->persist($dataProvider);

        // Create project A and B.
        $projectA = new Project();
        $projectA->setDataProvider($dataProvider);
        $projectA->setName('Project A');
        $projectA->setProjectTrackerId('proj-a');
        $projectA->setProjectTrackerKey('proj-a');
        $projectA->setProjectTrackerProjectUrl('www.example.comproj-a');
        $entityManager->persist($projectA);

        $projectB = new Project();
        $projectB->setDataProvider($dataProvider);
        $projectB->setName('Project B');
        $projectB->setProjectTrackerId('proj-b');
        $projectB->setProjectTrackerKey('proj-b');
        $projectB->setProjectTrackerProjectUrl('www.example.comproj-b');
        $entityManager->persist($projectB);

        // Create issue C on project A.
        $issueC = new Issue();
        $issueC->setDataProvider($dataProvider);
        $issueC->setProject($projectA);
        $issueC->setProjectTrackerId('issue-c');
        $issueC->setProjectTrackerKey('issue-c');
        $issueC->setName('Issue C');
        $issueC->setStatus(IssueStatusEnum::NEW);
        $issueC->setLinkToIssue('www.example.comissue-c');
        $entityManager->persist($issueC);

        // Create worklogs D and E linked to issue C and project A.
        $worklogD = new Worklog();
        $worklogD->setDataProvider($dataProvider);
        $worklogD->setProject($projectA);
        $worklogD->setIssue($issueC);
        $worklogD->setWorklogId(100);
        $worklogD->setWorker('test@test');
        $worklogD->setTimeSpentSeconds(3600);
        $worklogD->setStarted(new \DateTime());
        $worklogD->setProjectTrackerIssueId('issue-c');
        $worklogD->setDescription('Worklog D');
        $worklogD->setKind(BillableKindsEnum::GENERAL_BILLABLE);
        $entityManager->persist($worklogD);

        $worklogE = new Worklog();
        $worklogE->setDataProvider($dataProvider);
        $worklogE->setProject($projectA);
        $worklogE->setIssue($issueC);
        $worklogE->setWorklogId(101);
        $worklogE->setWorker('test@test');
        $worklogE->setTimeSpentSeconds(7200);
        $worklogE->setStarted(new \DateTime());
        $worklogE->setProjectTrackerIssueId('issue-c');
        $worklogE->setDescription('Worklog E');
        $worklogE->setKind(BillableKindsEnum::GENERAL_BILLABLE);
        $entityManager->persist($worklogE);

        $entityManager->flush();

        // Verify worklogs are on project A.
        $this->assertEquals('proj-a', $worklogD->getProject()->getProjectTrackerId());
        $this->assertEquals('proj-a', $worklogE->getProject()->getProjectTrackerId());

        // Move issue C from project A to project B via upsertIssue.
        $service->upsertIssue(new DataProviderIssueData(
            projectTrackerId: 'issue-c',
            dataProviderId: $dataProvider->getId(),
            projectTrackerProjectId: 'proj-b',
            name: 'Issue C',
            epics: [],
            plannedHours: 0.0,
            remainingHours: 0.0,
            worker: null,
            status: IssueStatusEnum::NEW,
            dueDate: null,
            resolutionDate: null,
            fetchTime: new \DateTime(),
            url: 'www.example.comissue-c',
            sourceModifiedDate: null,
            versionId: null,
            disableModifiedAtCheck: true,
        ));

        // Clear identity map to get fresh state from DB.
        $entityManager->clear();

        // Verify issue is now on project B.
        $issue = $issueRepository->findOneBy(['projectTrackerId' => 'issue-c', 'dataProvider' => $dataProvider->getId()]);
        $this->assertEquals('proj-b', $issue->getProject()->getProjectTrackerId());

        // Verify both worklogs are now on project B.
        $worklogD = $worklogRepository->findOneBy(['worklogId' => 100, 'dataProvider' => $dataProvider->getId()]);
        $worklogE = $worklogRepository->findOneBy(['worklogId' => 101, 'dataProvider' => $dataProvider->getId()]);
        $this->assertEquals('proj-b', $worklogD->getProject()->getProjectTrackerId());
        $this->assertEquals('proj-b', $worklogE->getProject()->getProjectTrackerId());
    }
}
