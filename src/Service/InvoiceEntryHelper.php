<?php

namespace App\Service;

use App\Entity\InvoiceEntry;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InvoiceEntryHelper
{
    private readonly array $options;

    public function __construct(
        array $options
    ) {
        $this->options = $this->resolveOptions($options);
    }

    /**
     * Get all configured accounts.
     *
     * @param string|null $account
     *   An account that must exist in the result
     *
     * @return array<string, string>
     *   label => id
     */
    public function getAccountOptions(?string $account): array
    {
        $options = [];

        $accounts = $this->getAccounts($account);
        foreach ($accounts as $id => $info) {
            $options[$info['label'] ?? $id] = $id;
        }

        return $options;
    }

    /**
     * Get ID of default account.
     *
     * @return string|null
     *   The default account ID if any
     */
    public function getDefaultAccount(): ?string
    {
        $accounts = $this->getAccounts(null);

        foreach ($accounts as $id => $info) {
            if ((bool) ($info['default'] ?? false)) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Get ID of product account.
     *
     * @return string|null
     *   The product account ID if any
     */
    public function getProductAccount(): ?string
    {
        $accounts = $this->getAccounts(null);

        foreach ($accounts as $id => $info) {
            if ((bool) ($info['product'] ?? false)) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Get account label based on configured accounts.
     */
    public function getAccountLabel(string $account): string
    {
        $accounts = $this->getAccounts(null);

        return $accounts[$account]['label'] ?? $account;
    }

    /**
     * Get account invoice entry pretix based on configured accounts.
     */
    public function getAccountInvoiceEntryPrefix(string $account): ?string
    {
        $accounts = $this->getAccounts(null);

        return $accounts[$account]['invoice_entry_prefix'] ?? null;
    }

    /**
     * Decide if an invoice entry is editable.
     */
    public function isEditable(InvoiceEntry $entry): bool
    {
        return null === $entry->getInvoice()?->getProjectBilling()
            || count($this->getAccountOptions(null)) > 1;
    }

    /**
     * @param string $account
     *
     * @return array
     */
    private function getAccounts(?string $account): array
    {
        $accounts = $this->options['accounts'] ?? [];

        // Make sure that the default account exists.
        if (isset($account) && !isset($accounts[$account])) {
            $accounts[$account] = [
                'label' => $account,
            ];
        }

        return $accounts;
    }

    private function resolveOptions(array $options): array
    {
        return (new OptionsResolver())
            ->setRequired(['accounts'])
            ->setAllowedTypes('accounts', 'array')
            ->setDefault('accounts', static function (OptionsResolver $resolver, Options $parent): void {
                $resolver
                    ->setPrototype(true)
                    ->setRequired('label')
                    ->setAllowedTypes('label', 'string')
                    ->setDefaults([
                        'default' => false,
                        'product' => false,
                        'invoice_entry_prefix' => null,
                    ])
                    ->setAllowedTypes('default', 'bool')
                    ->setAllowedTypes('product', 'bool');
            })
            ->setAllowedValues('accounts', function (array $values) {
                if (empty($values)) {
                    throw new InvalidOptionsException('At least one invoice entry account must be defined.');
                }

                if (count($values) > 1) {
                    $formatLabels = static function (array $accounts): string {
                        $labels = array_map(static fn (array $spec) => $spec['label'], $accounts);

                        return empty($labels) ? 'none' : join(', ', array_map('json_encode', $labels));
                    };

                    $defaults = array_filter($values, static fn (array $spec) => $spec['default']);
                    if (1 !== count($defaults)) {
                        throw new InvalidOptionsException(sprintf('Exactly one invoice entry account must be "default"; %s found.', $formatLabels($defaults)));
                    }

                    $products = array_filter($values, static fn (array $spec) => $spec['product']);
                    if (1 !== count($products)) {
                        throw new InvalidOptionsException(sprintf('Exactly one invoice entry account must be "product"; %s found.', $formatLabels($products)));
                    }

                    if ($products === $defaults) {
                        throw new InvalidOptionsException(sprintf('The account %s cannot be both "default" and "product".', array_key_first($defaults)));
                    }
                }

                return true;
            })
            ->addNormalizer('accounts', function (Options $options, array $values) {
                // Make sure that a single account is the default account.
                if (1 === count($values)) {
                    $values[array_key_first($values)]['default'] = true;
                }

                return $values;
            })

            ->resolve($options);
    }
}
