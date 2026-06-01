<?php

namespace App\Tests\Unit\Service;

use App\Entity\DataProvider;
use App\Entity\Project;
use App\Service\LeantimeUrlGenerator;
use PHPUnit\Framework\TestCase;

class LeantimeUrlGeneratorTest extends TestCase
{
    public function testBaseUrlReturnsTrimmedUrl(): void
    {
        $generator = new LeantimeUrlGenerator();

        $this->assertSame('https://leantime.test', $generator->baseUrl('https://leantime.test'));
        $this->assertSame('https://leantime.test', $generator->baseUrl('https://leantime.test/'));
    }

    public function testBaseUrlReturnsNullForEmptyInput(): void
    {
        $generator = new LeantimeUrlGenerator();

        $this->assertNull($generator->baseUrl(null));
        $this->assertNull($generator->baseUrl(''));
    }

    public function testBaseUrlForProjectResolvesFromDataProvider(): void
    {
        $dataProvider = new DataProvider();
        $dataProvider->setUrl('https://leantime.test/');

        $project = new Project();
        $project->setDataProvider($dataProvider);

        $generator = new LeantimeUrlGenerator();

        $this->assertSame('https://leantime.test', $generator->baseUrlForProject($project));
    }

    public function testBaseUrlForProjectReturnsNullWhenProjectHasNoDataProvider(): void
    {
        $project = new Project();

        $generator = new LeantimeUrlGenerator();

        $this->assertNull($generator->baseUrlForProject($project));
    }

    public function testBaseUrlForProjectReturnsNullForNullProject(): void
    {
        $generator = new LeantimeUrlGenerator();

        $this->assertNull($generator->baseUrlForProject(null));
    }
}
