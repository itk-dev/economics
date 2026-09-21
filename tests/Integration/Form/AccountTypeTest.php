<?php

namespace App\Tests\Integration\Form;

use App\Entity\Account;
use App\Form\AccountType;

class AccountTypeTest extends AbstractFormTestCase
{
    public function testFormExposesExpectedFields(): void
    {
        $this->assertHasFields(AccountType::class, ['name', 'value']);
    }

    public function testDataClassIsAccount(): void
    {
        $form = $this->createForm(AccountType::class);

        $this->assertSame(Account::class, $form->getConfig()->getOption('data_class'));
    }

    public function testSubmitMapsDataToAccount(): void
    {
        $account = new Account();
        $form = $this->createForm(AccountType::class, $account);

        $form->submit([
            'name' => 'Test Account',
            'value' => '12345',
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
        $this->assertSame('Test Account', $account->getName());
        $this->assertSame('12345', $account->getValue());
    }

    public function testFieldsAreRequired(): void
    {
        $form = $this->createForm(AccountType::class, new Account());

        $this->assertTrue($form->get('name')->isRequired());
        $this->assertTrue($form->get('value')->isRequired());
    }
}
