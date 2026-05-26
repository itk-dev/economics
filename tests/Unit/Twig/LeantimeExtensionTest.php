<?php

namespace App\Tests\Unit\Twig;

use App\Entity\DataProvider;
use App\Entity\Project;
use App\Service\LeantimeUrlGenerator;
use App\Twig\LeantimeExtension;
use PHPUnit\Framework\TestCase;

class LeantimeExtensionTest extends TestCase
{
    public function testGetFunctionsExposesLeantimeUrl(): void
    {
        $extension = new LeantimeExtension(new LeantimeUrlGenerator());

        $functions = $extension->getFunctions();

        $this->assertCount(1, $functions);
        $this->assertSame('leantime_url', $functions[0]->getName());
    }

    public function testLeantimeUrlReturnsDataProviderBaseUrl(): void
    {
        $dataProvider = new DataProvider();
        $dataProvider->setUrl('https://leantime.test/');

        $project = new Project();
        $project->setDataProvider($dataProvider);

        $extension = new LeantimeExtension(new LeantimeUrlGenerator());

        $this->assertSame('https://leantime.test', $extension->leantimeUrl($project));
    }

    public function testLeantimeUrlReturnsNullForNullProject(): void
    {
        $extension = new LeantimeExtension(new LeantimeUrlGenerator());

        $this->assertNull($extension->leantimeUrl(null));
    }
}
