<?php

namespace App\Tests\Unit\Twig;

use App\Entity\Project;
use App\Service\LeantimeUrlGenerator;
use App\Twig\LeantimeExtension;
use PHPUnit\Framework\TestCase;
use Twig\TwigFunction;

class LeantimeExtensionTest extends TestCase
{
    public function testGetFunctionsExposesLeantimeUrl(): void
    {
        $extension = new LeantimeExtension(new LeantimeUrlGenerator('https://leantime.test'));

        $functions = $extension->getFunctions();

        $this->assertCount(1, $functions);
        $this->assertInstanceOf(TwigFunction::class, $functions[0]);
        $this->assertSame('leantime_url', $functions[0]->getName());
    }

    public function testLeantimeUrlDelegatesToGenerator(): void
    {
        $project = new Project();
        $project->setProjectTrackerId('proj-9');

        $extension = new LeantimeExtension(new LeantimeUrlGenerator('https://leantime.test'));

        $this->assertSame(
            'https://leantime.test/projects/changeCurrentProject/proj-9',
            $extension->leantimeUrl($project),
        );
    }

    public function testLeantimeUrlReturnsNullForNullProject(): void
    {
        $extension = new LeantimeExtension(new LeantimeUrlGenerator('https://leantime.test'));

        $this->assertNull($extension->leantimeUrl(null));
    }
}
