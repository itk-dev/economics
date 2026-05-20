<?php

namespace App\Twig;

use App\Entity\Project;
use App\Service\LeantimeUrlGenerator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class LeantimeExtension extends AbstractExtension
{
    public function __construct(
        private readonly LeantimeUrlGenerator $generator,
    ) {
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('leantime_url', $this->leantimeUrl(...)),
        ];
    }

    public function leantimeUrl(?Project $project): ?string
    {
        return $this->generator->forProject($project);
    }
}
