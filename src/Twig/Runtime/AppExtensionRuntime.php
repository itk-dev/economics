<?php

namespace App\Twig\Runtime;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\RuntimeExtensionInterface;

class AppExtensionRuntime implements RuntimeExtensionInterface
{
    private ?\NumberFormatter $hoursFormatter;
    private ?\NumberFormatter $priceFormatter;
    private ?\NumberFormatter $quantityFormatter;

    public function __construct(
        private readonly RequestStack $requestStack
    ) {
    }

    public function formatHours($value)
    {
        if (!isset($this->hoursFormatter)) {
            $this->hoursFormatter = $this->createNumberFormatter(\NumberFormatter::DECIMAL);
        }

        return $this->hoursFormatter->format($value);
    }

    public function formatPrice($value)
    {
        if (!isset($this->priceFormatter)) {
            $this->priceFormatter = $this->createNumberFormatter(\NumberFormatter::CURRENCY);
            $this->priceFormatter->setSymbol(\NumberFormatter::CURRENCY_SYMBOL, '');
        }

        return $this->priceFormatter->format($value);
    }

    public function formatQuantity($value)
    {
        if (!isset($this->quantityFormatter)) {
            $this->quantityFormatter = $this->createNumberFormatter(\NumberFormatter::DECIMAL);
            $this->quantityFormatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 3);
            $this->quantityFormatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 3);
        }

        return $this->quantityFormatter->format($value);
    }

    private function createNumberFormatter(int $style): \NumberFormatter
    {
        $request = $this->requestStack->getCurrentRequest();
        $formatter = new \NumberFormatter($request?->getLocale() ?? 'da', $style);

        // @todo get this from configuration.
        $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 2);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 2);

        return $formatter;
    }
}
