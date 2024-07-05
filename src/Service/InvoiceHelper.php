<?php

namespace App\Service;

use App\Entity\Invoice;
use App\Entity\InvoiceEntry;
use Doctrine\DBAL\Driver\Exception;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InvoiceHelper
{
    private readonly array $options;

    public function __construct(
        private readonly HtmlHelper $htmlHelper,
        array $options,
    ) {
        $this->options = $this->resolveOptions($options);
    }

    public function getOneInvoicePerIssue(): bool
    {
        return $this->options['one_invoice_per_issue'];
    }

    public function getSetInvoiceDescriptionFromIssueDescription(): bool
    {
        return $this->options['set_invoice_description_from_issue_description'];
    }

    public function getInvoiceDescription(?string $description): ?string
    {
        if (empty($description)) {
            return $description;
        }

        $heading = $this->getInvoiceDescriptionIssueHeading();
        if ($description = $this->htmlHelper->getSection($description, $heading)) {
            foreach ($this->getInvoiceDescriptionElementReplacements() as $elementName => [$before, $after]) {
                $description = $this->htmlHelper->element2separator($description, $elementName, $before, $after);
            }

            $description = strip_tags($description);

            // HACK! Replace some duplicated punctuation.
            $description = preg_replace('/([;:] )\1/', '$1', $description);

            return mb_strcut(trim($description), 0, Invoice::DESCRIPTION_MAX_LENGTH);
        }

        return null;
    }

    public function getInvoiceDescriptionIssueHeading(): string
    {
        return $this->options['invoice_description_issue_heading'];
    }

    public function getInvoiceDescriptionElementReplacements(): array
    {
        return $this->options['invoice_description_element_replacements'];
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
    public function isEntryEditable(InvoiceEntry $entry): bool
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

    public function getIssueFarPastCutoffDate(): ?\DateTimeInterface
    {
        return $this->options['invoice_issue_far_past_cutoff_date'];
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
                    ->setAllowedTypes('product', 'bool')
                    ->setAllowedTypes('invoice_entry_prefix', ['null', 'string']);
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

            ->setDefault('one_invoice_per_issue', false)
            ->setAllowedTypes('one_invoice_per_issue', 'bool')

            ->setDefault('set_invoice_description_from_issue_description', false)
            ->setAllowedTypes('set_invoice_description_from_issue_description', 'bool')

            ->setDefault('invoice_description_issue_heading', '')
            ->setAllowedTypes('invoice_description_issue_heading', 'string')

            ->setDefault('invoice_description_element_replacements', [])
            ->setAllowedTypes('invoice_description_element_replacements', 'array')

            ->setDefault('invoice_issue_far_past_cutoff_date', null)
            ->setAllowedTypes('invoice_issue_far_past_cutoff_date', ['null', 'string'])
            ->setAllowedValues('invoice_issue_far_past_cutoff_date', static function (?string $value) {
                try {
                    new \DateTimeImmutable($value);

                    return true;
                } catch (Exception) {
                    return false;
                }
            })
            ->setNormalizer('invoice_issue_far_past_cutoff_date', static fn (Options $options, ?string $value) => new \DateTimeImmutable($value ?: '0000-00-00'))
            ->resolve($options);
    }
}
