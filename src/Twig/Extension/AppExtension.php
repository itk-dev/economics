<?php

namespace App\Twig\Extension;

use App\Twig\Runtime\AppExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            // If your filter generates SAFE HTML, you should add a third
            // parameter: ['is_safe' => ['html']]
            // Reference: https://twig.symfony.com/doc/3.x/advanced.html#automatic-escaping
            new TwigFilter('format_price', [AppExtensionRuntime::class, 'formatPrice'], ['is_safe' => ['html']]),
            new TwigFilter('format_hours', [AppExtensionRuntime::class, 'formatHours'], ['is_safe' => ['html']]),
            new TwigFilter('format_quantity', [AppExtensionRuntime::class, 'formatQuantity'], ['is_safe' => ['html']]),
        ];
    }
}
