<?php

namespace App\Tests\Unit\Service;

use App\Entity\Project;
use App\Service\LeantimeUrlGenerator;
use PHPUnit\Framework\TestCase;

class LeantimeUrlGeneratorTest extends TestCase
{
    public function testForProjectTrackerIdBuildsUrl(): void
    {
        $generator = new LeantimeUrlGenerator('https://leantime.test');

        $this->assertSame(
            'https://leantime.test/projects/changeCurrentProject/42',
            $generator->forProjectTrackerId('42'),
        );
    }

    public function testForProjectTrackerIdTrimsTrailingSlash(): void
    {
        $generator = new LeantimeUrlGenerator('https://leantime.test/');

        $this->assertSame(
            'https://leantime.test/projects/changeCurrentProject/abc',
            $generator->forProjectTrackerId('abc'),
        );
    }

    public function testForProjectTrackerIdReturnsNullWhenBaseUrlEmpty(): void
    {
        $generator = new LeantimeUrlGenerator('');

        $this->assertNull($generator->forProjectTrackerId('42'));
    }

    public function testForProjectTrackerIdReturnsNullWhenTrackerIdNull(): void
    {
        $generator = new LeantimeUrlGenerator('https://leantime.test');

        $this->assertNull($generator->forProjectTrackerId(null));
    }

    public function testForProjectTrackerIdReturnsNullWhenTrackerIdEmpty(): void
    {
        $generator = new LeantimeUrlGenerator('https://leantime.test');

        $this->assertNull($generator->forProjectTrackerId(''));
    }

    public function testForProjectDelegatesToTrackerId(): void
    {
        $project = new Project();
        $project->setProjectTrackerId('proj-123');

        $generator = new LeantimeUrlGenerator('https://leantime.test');

        $this->assertSame(
            'https://leantime.test/projects/changeCurrentProject/proj-123',
            $generator->forProject($project),
        );
    }

    public function testForProjectReturnsNullForNullProject(): void
    {
        $generator = new LeantimeUrlGenerator('https://leantime.test');

        $this->assertNull($generator->forProject(null));
    }
}
