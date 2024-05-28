<?php

namespace App\Service;

use App\Entity\InvoiceEntry;

class InvoiceEntryHelper
{
    public function __construct(
        private readonly array $options
    ) {
    }

    /**
     * Get all configured accounts.
     *
     * @return array<string, string>
     */
    public function getAccounts(): array
    {
        return $this->options['accounts'] ?? [];
    }

    /**
     * Decide if an invoice entry is editable.
     */
    public function isEditable(InvoiceEntry $entry): bool
    {
        return null === $entry->getInvoice()?->getProjectBilling()
            || !empty($this->getAccounts());
    }

    /**
     * Get account display name based on configured accounts.
     */
    public function getAccountDisplayName(string $account): string
    {
        $labels = array_flip($this->getAccounts());

        return $labels[$account] ?? $account;
    }
}
