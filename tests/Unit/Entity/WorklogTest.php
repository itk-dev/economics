<?php

namespace App\Tests\Unit\Entity;

use App\Entity\DataProvider;
use App\Entity\InvoiceEntry;
use App\Entity\Issue;
use App\Entity\Project;
use App\Entity\Worklog;
use App\Enum\BillableKindsEnum;
use PHPUnit\Framework\TestCase;

class WorklogTest extends TestCase
{
    private Worklog $worklog;

    protected function setUp(): void
    {
        $this->worklog = new Worklog();
    }

    public function testScalarAccessors(): void
    {
        $started = new \DateTime('2026-03-01 09:00:00');

        $this->worklog->setWorklogId(4711);
        $this->worklog->setIsBilled(true);
        $this->worklog->setDescription('Pair programming');
        $this->worklog->setWorker('worker@example.com');
        $this->worklog->setTimeSpentSeconds(3600);
        $this->worklog->setStarted($started);
        $this->worklog->setBilledSeconds(1800);
        $this->worklog->setProjectTrackerIssueId('ECON-42');
        $this->worklog->setKind(BillableKindsEnum::DEVELOPMENT);

        $this->assertSame(4711, $this->worklog->getWorklogId());
        $this->assertTrue($this->worklog->isBilled());
        $this->assertSame('Pair programming', $this->worklog->getDescription());
        $this->assertSame('worker@example.com', $this->worklog->getWorker());
        $this->assertSame(3600, $this->worklog->getTimeSpentSeconds());
        $this->assertSame($started, $this->worklog->getStarted());
        $this->assertSame(1800, $this->worklog->getBilledSeconds());
        $this->assertSame('ECON-42', $this->worklog->getProjectTrackerIssueId());
        $this->assertSame(BillableKindsEnum::DEVELOPMENT, $this->worklog->getKind());
    }

    public function testNullableAccessorsAcceptNull(): void
    {
        $this->worklog->setDescription(null);
        $this->worklog->setBilledSeconds(null);
        $this->worklog->setKind(null);
        $this->worklog->setInvoiceEntry(null);
        $this->worklog->setProject(null);
        $this->worklog->setIssue(null);

        $this->assertNull($this->worklog->getDescription());
        $this->assertNull($this->worklog->getBilledSeconds());
        $this->assertNull($this->worklog->getKind());
        $this->assertNull($this->worklog->getInvoiceEntry());
        $this->assertNull($this->worklog->getProject());
        $this->assertNull($this->worklog->getIssue());
    }

    public function testRelationAccessors(): void
    {
        $invoiceEntry = new InvoiceEntry();
        $project = new Project();
        $issue = new Issue();
        $dataProvider = new DataProvider();

        $this->worklog->setInvoiceEntry($invoiceEntry);
        $this->worklog->setProject($project);
        $this->worklog->setIssue($issue);
        $this->worklog->setDataProvider($dataProvider);

        $this->assertSame($invoiceEntry, $this->worklog->getInvoiceEntry());
        $this->assertSame($project, $this->worklog->getProject());
        $this->assertSame($issue, $this->worklog->getIssue());
        $this->assertSame($dataProvider, $this->worklog->getDataProvider());
    }

    public function testSynchronizationDates(): void
    {
        $fetched = new \DateTime('2026-01-01');
        $modified = new \DateTime('2026-02-01');
        $deleted = new \DateTime('2026-03-01');

        $this->worklog->setFetchDate($fetched);
        $this->worklog->setSourceModifiedDate($modified);
        $this->worklog->setSourceDeletedDate($deleted);

        $this->assertSame($fetched, $this->worklog->getFetchDate());
        $this->assertSame($modified, $this->worklog->getSourceModifiedDate());
        $this->assertSame($deleted, $this->worklog->getSourceDeletedDate());
    }

    public function testSettersAreFluent(): void
    {
        $this->assertSame($this->worklog, $this->worklog->setWorklogId(1));
        $this->assertSame($this->worklog, $this->worklog->setInvoiceEntry(null));
        $this->assertSame($this->worklog, $this->worklog->setIsBilled(false));
        $this->assertSame($this->worklog, $this->worklog->setDescription(null));
        $this->assertSame($this->worklog, $this->worklog->setWorker('w@example.com'));
        $this->assertSame($this->worklog, $this->worklog->setTimeSpentSeconds(0));
        $this->assertSame($this->worklog, $this->worklog->setStarted(new \DateTime()));
        $this->assertSame($this->worklog, $this->worklog->setProject(null));
        $this->assertSame($this->worklog, $this->worklog->setBilledSeconds(null));
        $this->assertSame($this->worklog, $this->worklog->setIssue(null));
        $this->assertSame($this->worklog, $this->worklog->setProjectTrackerIssueId('1'));
        $this->assertSame($this->worklog, $this->worklog->setKind(null));
    }
}
