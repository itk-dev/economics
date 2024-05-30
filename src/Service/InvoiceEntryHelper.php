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
     * @param string|null $account
     *   An account that must exist in the result
     *
     * @return array<string, string>
     */
    public function getAccounts(?string $account): array
    {
        $accounts = $this->options['accounts'] ?? [];

        // Make sure that the default account exists.
        if (isset($account) && !in_array($account, $accounts, true)) {
            $accounts[$account] = $account;
        }

        return $accounts;
    }

    /**
     * Decide if an invoice entry is editable.
     */
    public function isEditable(InvoiceEntry $entry): bool
    {
        return null === $entry->getInvoice()?->getProjectBilling()
            || !empty($this->getAccounts(null));
    }

    /**
     * Get account display name based on configured accounts.
     */
    public function getAccountDisplayName(string $account): string
    {
        $labels = array_flip($this->getAccounts($account));

        return $labels[$account] ?? $account;
    }
}
