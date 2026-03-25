<?php

namespace App\Tests\Repository;

use App\Entity\DataProvider;
use App\Entity\Issue;
use App\Entity\Project;
use App\Entity\Worklog;
use App\Repository\WorklogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class WorklogRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private WorklogRepository $worklogRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->worklogRepository = $container->get(WorklogRepository::class);
    }

    public function testAnonymizeWorklogsBeforeDate(): void
    {
        // Create test data
        $dataProvider = new DataProvider();
        $dataProvider->setName('Test Provider');
        $dataProvider->setUrl('https://test.example.com');
        $dataProvider->setClass('TestClass');
        $this->entityManager->persist($dataProvider);

        $project = new Project();
        $project->setName('Test Project');
        $project->setProjectTrackerId('TEST-1');
        $project->setProjectTrackerProjectUrl('https://test.example.com/project/1');
        $project->setProjectTrackerKey('TEST-1');
        $project->setDataProvider($dataProvider);
        $this->entityManager->persist($project);

        $issue = new Issue();
        $issue->setName('Test Issue');
        $issue->setProjectTrackerId('ISSUE-1');
        $issue->setProjectTrackerKey('TEST-1');
        $issue->setLinkToIssue('https://test.example.com/issue/1');
        $issue->setProject($project);
        $issue->setDataProvider($dataProvider);
        $this->entityManager->persist($issue);

        // Create worklogs with different dates
        $oldWorklog = new Worklog();
        $oldWorklog->setWorklogId(1001);
        $oldWorklog->setDescription('Old worklog description');
        $oldWorklog->setWorker('test@example.com');
        $oldWorklog->setTimeSpentSeconds(3600);
        $oldWorklog->setStarted(new \DateTime('2020-01-01'));
        $oldWorklog->setProject($project);
        $oldWorklog->setIssue($issue);
        $oldWorklog->setProjectTrackerIssueId('ISSUE-1');
        $oldWorklog->setDataProvider($dataProvider);
        $this->entityManager->persist($oldWorklog);

        $recentWorklog = new Worklog();
        $recentWorklog->setWorklogId(1002);
        $recentWorklog->setDescription('Recent worklog description');
        $recentWorklog->setWorker('test@example.com');
        $recentWorklog->setTimeSpentSeconds(7200);
        $recentWorklog->setStarted(new \DateTime('2025-01-01'));
        $recentWorklog->setProject($project);
        $recentWorklog->setIssue($issue);
        $recentWorklog->setProjectTrackerIssueId('ISSUE-1');
        $recentWorklog->setDataProvider($dataProvider);
        $this->entityManager->persist($recentWorklog);

        $this->entityManager->flush();

        $oldWorklogId = $oldWorklog->getId();
        $recentWorklogId = $recentWorklog->getId();

        // Execute anonymization for worklogs before 2024-01-01
        $anonymizeBefore = new \DateTime('2024-01-01');
        $affectedRows = $this->worklogRepository->anonymizeWorklogs($anonymizeBefore);

        // Assert that one row was affected
        $this->assertEquals(1, $affectedRows);

        // Refresh entities from database
        $this->entityManager->clear();
        $oldWorklog = $this->worklogRepository->find($oldWorklogId);
        $recentWorklog = $this->worklogRepository->find($recentWorklogId);

        // Assert old worklog was anonymized
        $this->assertEquals('worklog '.$oldWorklogId, $oldWorklog->getDescription());
        $this->assertNotNull($oldWorklog->getAnonymizedDate());
        $this->assertInstanceOf(\DateTimeInterface::class, $oldWorklog->getAnonymizedDate());

        // Assert recent worklog was not anonymized
        $this->assertEquals('Recent worklog description', $recentWorklog->getDescription());
        $this->assertNull($recentWorklog->getAnonymizedDate());

        // Cleanup
        $this->entityManager->remove($oldWorklog);
        $this->entityManager->remove($recentWorklog);
        $this->entityManager->remove($issue);
        $this->entityManager->remove($project);
        $this->entityManager->remove($dataProvider);
        $this->entityManager->flush();
    }

    public function testAnonymizeWorklogsWithNoMatchingRecords(): void
    {
        // Execute anonymization for very old date where no worklogs exist
        $anonymizeBefore = new \DateTime('1900-01-01');
        $affectedRows = $this->worklogRepository->anonymizeWorklogs($anonymizeBefore);

        // Assert that no rows were affected
        $this->assertEquals(0, $affectedRows);
    }

    public function testAnonymizeWorklogsSetsCorrectAnonymizedDate(): void
    {
        // Create test data
        $dataProvider = new DataProvider();
        $dataProvider->setName('Test Provider 2');
        $dataProvider->setUrl('https://test.example.com');
        $dataProvider->setClass('TestClass');
        $this->entityManager->persist($dataProvider);

        $project = new Project();
        $project->setName('Test Project');
        $project->setProjectTrackerId('TEST-2');
        $project->setProjectTrackerProjectUrl('https://test.example.com/project/2');
        $project->setProjectTrackerKey('TEST-2');
        $project->setDataProvider($dataProvider);
        $this->entityManager->persist($project);

        $issue = new Issue();
        $issue->setName('Test Issue 2');
        $issue->setProjectTrackerId('ISSUE-2');
        $issue->setProjectTrackerKey('TEST-2');
        $issue->setLinkToIssue('https://test.example.com/issue/2');
        $issue->setProject($project);
        $issue->setDataProvider($dataProvider);
        $this->entityManager->persist($issue);

        $worklog = new Worklog();
        $worklog->setWorklogId(2001);
        $worklog->setDescription('Test description');
        $worklog->setWorker('test@example.com');
        $worklog->setTimeSpentSeconds(3600);
        $worklog->setStarted(new \DateTime('2020-01-01'));
        $worklog->setProject($project);
        $worklog->setIssue($issue);
        $worklog->setProjectTrackerIssueId('ISSUE-2');
        $worklog->setDataProvider($dataProvider);
        $this->entityManager->persist($worklog);
        $this->entityManager->flush();

        $worklogId = $worklog->getId();
        $beforeAnonymization = new \DateTime();

        // Execute anonymization
        $anonymizeBefore = new \DateTime('2024-01-01');
        $this->worklogRepository->anonymizeWorklogs($anonymizeBefore);

        // Refresh entity
        $this->entityManager->clear();
        $worklog = $this->worklogRepository->find($worklogId);

        $afterAnonymization = new \DateTime();

        // Assert anonymizedDate is set to approximately now
        $this->assertNotNull($worklog->getAnonymizedDate());
        $this->assertGreaterThanOrEqual(
            $beforeAnonymization->getTimestamp(),
            $worklog->getAnonymizedDate()->getTimestamp()
        );
        $this->assertLessThanOrEqual(
            $afterAnonymization->getTimestamp(),
            $worklog->getAnonymizedDate()->getTimestamp()
        );

        // Cleanup
        $this->entityManager->remove($worklog);
        $this->entityManager->remove($issue);
        $this->entityManager->remove($project);
        $this->entityManager->remove($dataProvider);
        $this->entityManager->flush();
    }

    public function testAnonymizeWorklogsIgnoresAlreadyAnonymized(): void
    {
        // Create test data
        $dataProvider = new DataProvider();
        $dataProvider->setName('Test Provider 3');
        $dataProvider->setUrl('https://test.example.com');
        $dataProvider->setClass('TestClass');
        $this->entityManager->persist($dataProvider);

        $project = new Project();
        $project->setName('Test Project');
        $project->setProjectTrackerId('TEST-3');
        $project->setProjectTrackerProjectUrl('https://test.example.com/project/3');
        $project->setProjectTrackerKey('TEST-3');
        $project->setDataProvider($dataProvider);
        $this->entityManager->persist($project);

        $issue = new Issue();
        $issue->setName('Test Issue 3');
        $issue->setProjectTrackerId('ISSUE-3');
        $issue->setProjectTrackerKey('TEST-3');
        $issue->setLinkToIssue('https://test.example.com/issue/3');
        $issue->setProject($project);
        $issue->setDataProvider($dataProvider);
        $this->entityManager->persist($issue);

        // Create a worklog that has already been anonymized
        $alreadyAnonymized = new Worklog();
        $alreadyAnonymized->setWorklogId(3001);
        $alreadyAnonymized->setDescription('worklog+123');
        $alreadyAnonymized->setWorker('test@example.com');
        $alreadyAnonymized->setTimeSpentSeconds(3600);
        $alreadyAnonymized->setStarted(new \DateTime('2020-01-01'));
        $alreadyAnonymized->setAnonymizedDate(new \DateTime('2023-01-01'));
        $alreadyAnonymized->setProject($project);
        $alreadyAnonymized->setIssue($issue);
        $alreadyAnonymized->setProjectTrackerIssueId('ISSUE-3');
        $alreadyAnonymized->setDataProvider($dataProvider);
        $this->entityManager->persist($alreadyAnonymized);

        // Create a worklog that should be anonymized
        $notYetAnonymized = new Worklog();
        $notYetAnonymized->setWorklogId(3002);
        $notYetAnonymized->setDescription('Should be anonymized');
        $notYetAnonymized->setWorker('test@example.com');
        $notYetAnonymized->setTimeSpentSeconds(3600);
        $notYetAnonymized->setStarted(new \DateTime('2020-01-01'));
        $notYetAnonymized->setProject($project);
        $notYetAnonymized->setIssue($issue);
        $notYetAnonymized->setProjectTrackerIssueId('ISSUE-3');
        $notYetAnonymized->setDataProvider($dataProvider);
        $this->entityManager->persist($notYetAnonymized);

        $this->entityManager->flush();

        $alreadyAnonymizedId = $alreadyAnonymized->getId();
        $notYetAnonymizedId = $notYetAnonymized->getId();
        $originalAnonymizedDate = $alreadyAnonymized->getAnonymizedDate();

        // Execute anonymization
        $anonymizeBefore = new \DateTime('2024-01-01');
        $affectedRows = $this->worklogRepository->anonymizeWorklogs($anonymizeBefore);

        // Assert that only one row was affected (the not yet anonymized one)
        $this->assertEquals(1, $affectedRows);

        // Refresh entities
        $this->entityManager->clear();
        $alreadyAnonymized = $this->worklogRepository->find($alreadyAnonymizedId);
        $notYetAnonymized = $this->worklogRepository->find($notYetAnonymizedId);

        // Assert already anonymized worklog was not changed
        $this->assertEquals('worklog+123', $alreadyAnonymized->getDescription());
        $this->assertEquals(
            $originalAnonymizedDate->getTimestamp(),
            $alreadyAnonymized->getAnonymizedDate()->getTimestamp()
        );

        // Assert not yet anonymized worklog was anonymized
        $this->assertEquals('worklog+'.$notYetAnonymizedId, $notYetAnonymized->getDescription());
        $this->assertNotNull($notYetAnonymized->getAnonymizedDate());

        // Cleanup
        $this->entityManager->remove($alreadyAnonymized);
        $this->entityManager->remove($notYetAnonymized);
        $this->entityManager->remove($issue);
        $this->entityManager->remove($project);
        $this->entityManager->remove($dataProvider);
        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }
}
