<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Epic;
use App\Entity\Issue;
use App\Entity\IssueProduct;
use App\Entity\Project;
use App\Entity\Version;
use App\Entity\Worklog;
use App\Enum\IssueStatusEnum;
use PHPUnit\Framework\TestCase;

class IssueTest extends TestCase
{
    private Issue $issue;

    protected function setUp(): void
    {
        $this->issue = new Issue();
    }

    public function testCollectionsStartEmpty(): void
    {
        $this->assertCount(0, $this->issue->getVersions());
        $this->assertCount(0, $this->issue->getEpics());
        $this->assertCount(0, $this->issue->getWorklogs());
        $this->assertCount(0, $this->issue->getProducts());
    }

    public function testScalarAccessors(): void
    {
        $resolutionDate = new \DateTime('2026-04-01');
        $dueDate = new \DateTime('2026-05-01');

        $this->issue->setName('Fix the thing');
        $this->issue->setStatus(IssueStatusEnum::IN_PROGRESS);
        $this->issue->setAccountKey('ACC-1');
        $this->issue->setAccountId('1');
        $this->issue->setProjectTrackerId('42');
        $this->issue->setProjectTrackerKey('ECON-42');
        $this->issue->setResolutionDate($resolutionDate);
        $this->issue->setDueDate($dueDate);
        $this->issue->setWorker('worker@example.com');
        $this->issue->setLinkToIssue('https://tracker.example/issues/42');
        $this->issue->setPlanHours(12.5);

        $this->assertSame('Fix the thing', $this->issue->getName());
        $this->assertSame(IssueStatusEnum::IN_PROGRESS, $this->issue->getStatus());
        $this->assertSame('ACC-1', $this->issue->getAccountKey());
        $this->assertSame('1', $this->issue->getAccountId());
        $this->assertSame('42', $this->issue->getProjectTrackerId());
        $this->assertSame('ECON-42', $this->issue->getProjectTrackerKey());
        $this->assertSame($resolutionDate, $this->issue->getResolutionDate());
        $this->assertSame($dueDate, $this->issue->getDueDate());
        $this->assertSame('worker@example.com', $this->issue->getWorker());
        $this->assertSame('https://tracker.example/issues/42', $this->issue->getLinkToIssue());
        $this->assertSame(12.5, $this->issue->getPlanHours());
    }

    public function testNullableAccessorsAcceptNull(): void
    {
        $this->issue->setAccountKey(null);
        $this->issue->setAccountId(null);
        $this->issue->setResolutionDate(null);
        $this->issue->setDueDate(null);
        $this->issue->setWorker(null);
        $this->issue->setLinkToIssue(null);
        $this->issue->setPlanHours(null);
        $this->issue->setProject(null);

        $this->assertNull($this->issue->getAccountKey());
        $this->assertNull($this->issue->getAccountId());
        $this->assertNull($this->issue->getResolutionDate());
        $this->assertNull($this->issue->getDueDate());
        $this->assertNull($this->issue->getWorker());
        $this->assertNull($this->issue->getLinkToIssue());
        $this->assertNull($this->issue->getPlanHours());
        $this->assertNull($this->issue->getProject());
    }

    /**
     * `getHoursRemaining()` returns `planHours`, not the `hoursRemaining` it was
     * given. This test pins the current behaviour so the mismatch is visible —
     * `PlanningService` sums this value, so changing it changes planning output.
     */
    public function testGetHoursRemainingReturnsPlanHoursInsteadOfHoursRemaining(): void
    {
        $this->issue->setPlanHours(12.5);
        $this->issue->setHoursRemaining(3.0);

        $this->assertSame(12.5, $this->issue->getHoursRemaining());
        $this->assertNotSame(3.0, $this->issue->getHoursRemaining());
    }

    public function testProjectAccessor(): void
    {
        $project = new Project();
        $this->issue->setProject($project);

        $this->assertSame($project, $this->issue->getProject());
    }

    public function testVersionsAreManagedWithoutTouchingTheInverseSide(): void
    {
        $version = new Version();

        $this->issue->addVersion($version);
        $this->issue->addVersion($version);
        $this->assertCount(1, $this->issue->getVersions());
        $this->assertCount(0, $version->getIssues());

        $this->issue->removeVersion($version);
        $this->assertCount(0, $this->issue->getVersions());
    }

    public function testEpicsAreManagedWithoutTouchingTheInverseSide(): void
    {
        $epic = new Epic();

        $this->issue->addEpic($epic);
        $this->issue->addEpic($epic);
        $this->assertCount(1, $this->issue->getEpics());
        $this->assertCount(0, $epic->getIssues());

        $this->issue->removeEpic($epic);
        $this->assertCount(0, $this->issue->getEpics());
    }

    public function testAddWorklogSetsOwningSide(): void
    {
        $worklog = new Worklog();
        $this->issue->addWorklog($worklog);
        $this->issue->addWorklog($worklog);

        $this->assertCount(1, $this->issue->getWorklogs());
        $this->assertSame($this->issue, $worklog->getIssue());
    }

    public function testRemoveWorklogClearsOwningSide(): void
    {
        $worklog = new Worklog();
        $this->issue->addWorklog($worklog);
        $this->issue->removeWorklog($worklog);

        $this->assertCount(0, $this->issue->getWorklogs());
        $this->assertNull($worklog->getIssue());
    }

    public function testRemoveWorklogLeavesForeignOwnerAlone(): void
    {
        $other = new Issue();
        $worklog = new Worklog();
        $other->addWorklog($worklog);
        $this->issue->getWorklogs()->add($worklog);

        $this->issue->removeWorklog($worklog);

        $this->assertSame($other, $worklog->getIssue());
    }

    public function testAddProductSetsOwningSide(): void
    {
        $issueProduct = new IssueProduct();
        $this->issue->addProduct($issueProduct);
        $this->issue->addProduct($issueProduct);

        $this->assertCount(1, $this->issue->getProducts());
        $this->assertSame($this->issue, $issueProduct->getIssue());
    }

    public function testRemoveProductClearsOwningSide(): void
    {
        $issueProduct = new IssueProduct();
        $this->issue->addProduct($issueProduct);
        $this->issue->removeProduct($issueProduct);

        $this->assertCount(0, $this->issue->getProducts());
        $this->assertNull($issueProduct->getIssue());
    }

    public function testRemoveProductLeavesForeignOwnerAlone(): void
    {
        $other = new Issue();
        $issueProduct = new IssueProduct();
        $other->addProduct($issueProduct);
        $this->issue->getProducts()->add($issueProduct);

        $this->issue->removeProduct($issueProduct);

        $this->assertSame($other, $issueProduct->getIssue());
    }

    public function testSettersAreFluent(): void
    {
        $this->assertSame($this->issue, $this->issue->setName('Issue'));
        $this->assertSame($this->issue, $this->issue->setStatus(IssueStatusEnum::NEW));
        $this->assertSame($this->issue, $this->issue->setAccountKey(null));
        $this->assertSame($this->issue, $this->issue->setAccountId(null));
        $this->assertSame($this->issue, $this->issue->setProjectTrackerId('1'));
        $this->assertSame($this->issue, $this->issue->setProjectTrackerKey('ECON-1'));
        $this->assertSame($this->issue, $this->issue->setResolutionDate(null));
        $this->assertSame($this->issue, $this->issue->setProject(null));
        $this->assertSame($this->issue, $this->issue->setPlanHours(null));
        $this->assertSame($this->issue, $this->issue->setHoursRemaining(null));
        $this->assertSame($this->issue, $this->issue->setDueDate(null));
        $this->assertSame($this->issue, $this->issue->setWorker(null));
        $this->assertSame($this->issue, $this->issue->setLinkToIssue(null));
        $this->assertSame($this->issue, $this->issue->addVersion(new Version()));
        $this->assertSame($this->issue, $this->issue->addEpic(new Epic()));
        $this->assertSame($this->issue, $this->issue->addWorklog(new Worklog()));
        $this->assertSame($this->issue, $this->issue->addProduct(new IssueProduct()));
    }
}
