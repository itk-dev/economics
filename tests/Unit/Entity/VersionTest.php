<?php

namespace App\Tests\Unit\Entity;

use App\Entity\DataProvider;
use App\Entity\Issue;
use App\Entity\Project;
use App\Entity\Version;
use PHPUnit\Framework\TestCase;

class VersionTest extends TestCase
{
    private Version $version;

    protected function setUp(): void
    {
        $this->version = new Version();
    }

    public function testDefaults(): void
    {
        $this->assertCount(0, $this->version->getIssues());
        $this->assertTrue($this->version->isBillable());
    }

    public function testScalarAccessors(): void
    {
        $this->version->setName('PB-1');
        $this->version->setProjectTrackerId('42');
        $this->version->setIsBillable(false);

        $this->assertSame('PB-1', $this->version->getName());
        $this->assertSame('42', $this->version->getProjectTrackerId());
        $this->assertFalse($this->version->isBillable());
    }

    public function testProjectAccessor(): void
    {
        $project = new Project();
        $this->version->setProject($project);
        $this->assertSame($project, $this->version->getProject());

        $this->version->setProject(null);
        $this->assertNull($this->version->getProject());
    }

    public function testDataProviderAccessor(): void
    {
        $dataProvider = new DataProvider();
        $this->version->setDataProvider($dataProvider);

        $this->assertSame($dataProvider, $this->version->getDataProvider());
    }

    public function testToStringUsesName(): void
    {
        $this->version->setName('PB-1');

        $this->assertSame('PB-1', (string) $this->version);
    }

    public function testToStringFallsBackToIdWhenNameIsMissing(): void
    {
        $this->assertSame('', (string) $this->version);
    }

    public function testAddIssueKeepsBothSidesInSync(): void
    {
        $issue = new Issue();
        $this->version->addIssue($issue);
        $this->version->addIssue($issue);

        $this->assertCount(1, $this->version->getIssues());
        $this->assertTrue($issue->getVersions()->contains($this->version));
    }

    public function testRemoveIssueKeepsBothSidesInSync(): void
    {
        $issue = new Issue();
        $this->version->addIssue($issue);
        $this->version->removeIssue($issue);

        $this->assertCount(0, $this->version->getIssues());
        $this->assertCount(0, $issue->getVersions());
    }

    public function testSettersAreFluent(): void
    {
        $this->assertSame($this->version, $this->version->setName('PB-1'));
        $this->assertSame($this->version, $this->version->setProjectTrackerId('1'));
        $this->assertSame($this->version, $this->version->setProject(null));
        $this->assertSame($this->version, $this->version->setIsBillable(true));
        $this->assertSame($this->version, $this->version->addIssue(new Issue()));
    }
}
