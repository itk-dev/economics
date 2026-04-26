<?php

namespace App\Tests\Unit\Service;

use App\Entity\Invoice;
use App\Entity\InvoiceEntry;
use App\Entity\ProjectBilling;
use App\Enum\InvoiceEntryTypeEnum;
use App\Service\InvoiceEntryHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

class InvoiceEntryHelperTest extends TestCase
{
    public function testSingleAccountAutoBecomesDefault(): void
    {
        $helper = new InvoiceEntryHelper([
            'accounts' => [
                'ACC-1' => ['label' => 'Account 1'],
            ],
        ]);

        $this->assertSame('ACC-1', $helper->getDefaultAccount());
    }

    public function testMultipleAccountsWithValidConfig(): void
    {
        $helper = new InvoiceEntryHelper([
            'accounts' => [
                'ACC-1' => ['label' => 'Account 1', 'default' => true, 'product' => false],
                'ACC-2' => ['label' => 'Account 2', 'default' => false, 'product' => true],
            ],
        ]);

        $this->assertSame('ACC-1', $helper->getDefaultAccount());
        $this->assertSame('ACC-2', $helper->getProductAccount());
    }

    public function testEmptyAccountsThrowsException(): void
    {
        $this->expectException(InvalidOptionsException::class);

        new InvoiceEntryHelper([
            'accounts' => [],
        ]);
    }

    public function testMultipleAccountsWithoutDefaultThrows(): void
    {
        $this->expectException(InvalidOptionsException::class);

        new InvoiceEntryHelper([
            'accounts' => [
                'ACC-1' => ['label' => 'Account 1', 'default' => false, 'product' => false],
                'ACC-2' => ['label' => 'Account 2', 'default' => false, 'product' => true],
            ],
        ]);
    }

    public function testMultipleAccountsWithoutProductThrows(): void
    {
        $this->expectException(InvalidOptionsException::class);

        new InvoiceEntryHelper([
            'accounts' => [
                'ACC-1' => ['label' => 'Account 1', 'default' => true, 'product' => false],
                'ACC-2' => ['label' => 'Account 2', 'default' => false, 'product' => false],
            ],
        ]);
    }

    public function testGetAccountLabelKnownAccount(): void
    {
        $helper = new InvoiceEntryHelper([
            'accounts' => [
                'ACC-1' => ['label' => 'My Account'],
            ],
        ]);

        $this->assertSame('My Account', $helper->getAccountLabel('ACC-1'));
    }

    public function testGetAccountLabelUnknownAccountReturnsId(): void
    {
        $helper = new InvoiceEntryHelper([
            'accounts' => [
                'ACC-1' => ['label' => 'My Account'],
            ],
        ]);

        $this->assertSame('ACC-UNKNOWN', $helper->getAccountLabel('ACC-UNKNOWN'));
    }

    public function testGetAccountOptionsIncludesUnknownAccount(): void
    {
        $helper = new InvoiceEntryHelper([
            'accounts' => [
                'ACC-1' => ['label' => 'Account 1'],
            ],
        ]);

        $options = $helper->getAccountOptions('ACC-NEW');

        $this->assertArrayHasKey('Account 1', $options);
        $this->assertArrayHasKey('ACC-NEW', $options);
        $this->assertSame('ACC-1', $options['Account 1']);
        $this->assertSame('ACC-NEW', $options['ACC-NEW']);
    }

    public function testGetAccountOptionsNullAccount(): void
    {
        $helper = new InvoiceEntryHelper([
            'accounts' => [
                'ACC-1' => ['label' => 'Account 1'],
            ],
        ]);

        $options = $helper->getAccountOptions(null);

        $this->assertCount(1, $options);
        $this->assertSame('ACC-1', $options['Account 1']);
    }

    public function testIsEditableNoProjectBilling(): void
    {
        $helper = new InvoiceEntryHelper([
            'accounts' => [
                'ACC-1' => ['label' => 'Account 1'],
            ],
        ]);

        $invoice = new Invoice();
        $invoice->setName('Test');
        $invoice->setRecorded(false);
        // No project billing

        $entry = new InvoiceEntry();
        $entry->setEntryType(InvoiceEntryTypeEnum::MANUAL);
        $invoice->addInvoiceEntry($entry);

        $this->assertTrue($helper->isEditable($entry));
    }

    public function testIsEditableWithProjectBillingMultipleAccounts(): void
    {
        $helper = new InvoiceEntryHelper([
            'accounts' => [
                'ACC-1' => ['label' => 'Account 1', 'default' => true, 'product' => false],
                'ACC-2' => ['label' => 'Account 2', 'default' => false, 'product' => true],
            ],
        ]);

        $projectBilling = $this->createMock(ProjectBilling::class);
        $invoice = new Invoice();
        $invoice->setName('Test');
        $invoice->setRecorded(false);
        $invoice->setProjectBilling($projectBilling);

        $entry = new InvoiceEntry();
        $entry->setEntryType(InvoiceEntryTypeEnum::MANUAL);
        $invoice->addInvoiceEntry($entry);

        // Multiple accounts -> editable even with project billing
        $this->assertTrue($helper->isEditable($entry));
    }

    public function testIsEditableWithProjectBillingSingleAccount(): void
    {
        $helper = new InvoiceEntryHelper([
            'accounts' => [
                'ACC-1' => ['label' => 'Account 1'],
            ],
        ]);

        $projectBilling = $this->createMock(ProjectBilling::class);
        $invoice = new Invoice();
        $invoice->setName('Test');
        $invoice->setRecorded(false);
        $invoice->setProjectBilling($projectBilling);

        $entry = new InvoiceEntry();
        $entry->setEntryType(InvoiceEntryTypeEnum::MANUAL);
        $invoice->addInvoiceEntry($entry);

        // Single account + project billing -> not editable
        $this->assertFalse($helper->isEditable($entry));
    }

    public function testGetProductAccountSingleAccount(): void
    {
        $helper = new InvoiceEntryHelper([
            'accounts' => [
                'ACC-1' => ['label' => 'Account 1'],
            ],
        ]);

        // Single account is not automatically a product account
        $this->assertNull($helper->getProductAccount());
    }
}
