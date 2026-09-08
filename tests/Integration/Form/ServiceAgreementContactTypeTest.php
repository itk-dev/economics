<?php

namespace App\Tests\Integration\Form;

use App\Entity\ContactRole;
use App\Entity\ServiceAgreementContact;
use App\Form\ServiceAgreementContactType;

class ServiceAgreementContactTypeTest extends AbstractFormTestCase
{
    public function testFormExposesExpectedFields(): void
    {
        $this->assertHasFields(ServiceAgreementContactType::class, ['name', 'email', 'roles']);
    }

    public function testDataClassIsServiceAgreementContact(): void
    {
        $form = $this->createForm(ServiceAgreementContactType::class);

        $this->assertSame(ServiceAgreementContact::class, $form->getConfig()->getOption('data_class'));
    }

    public function testEmailAndRolesAreOptional(): void
    {
        $form = $this->createForm(ServiceAgreementContactType::class);

        foreach (['email', 'roles'] as $field) {
            $this->assertFalse($form->get($field)->isRequired(), sprintf('Field "%s" should be optional.', $field));
        }
    }

    public function testRolesIsAMultipleChoiceField(): void
    {
        $config = $this->createForm(ServiceAgreementContactType::class)->get('roles')->getConfig();

        $this->assertTrue($config->getOption('multiple'));
        $this->assertFalse($config->getOption('expanded'));
    }

    public function testRolesOffersEveryExistingRoleAsAChoice(): void
    {
        $this->persistRole('Fakturering');

        $choices = $this->createForm(ServiceAgreementContactType::class)->get('roles')->getConfig()->getOption('choices');

        $this->assertContains('Fakturering', $choices);
    }

    public function testSubmitMapsDataToContact(): void
    {
        $this->persistRole('Fakturering');

        $contact = new ServiceAgreementContact();
        $form = $this->createForm(ServiceAgreementContactType::class, $contact);

        $form->submit([
            'name' => 'Anna Hansen',
            'email' => 'anna@example.com',
            'roles' => ['Fakturering'],
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid(), (string) $form->getErrors(true));
        $this->assertSame('Anna Hansen', $contact->getName());
        $this->assertSame('anna@example.com', $contact->getEmail());
        $this->assertSame(['Fakturering'], $this->roleNames($contact));
    }

    public function testSubmitAcceptsARoleThatDoesNotExistYet(): void
    {
        $contact = new ServiceAgreementContact();
        $form = $this->createForm(ServiceAgreementContactType::class, $contact);

        $form->submit([
            'name' => 'Bo Jensen',
            'roles' => ['Daglig kontakt'],
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid(), (string) $form->getErrors(true));
        $this->assertSame(['Daglig kontakt'], $this->roleNames($contact));
    }

    public function testSubmitAcceptsAMixOfExistingAndNewRoles(): void
    {
        $this->persistRole('Fakturering');

        $contact = new ServiceAgreementContact();
        $form = $this->createForm(ServiceAgreementContactType::class, $contact);

        $form->submit([
            'name' => 'Carla Nielsen',
            'roles' => ['Fakturering', 'Teknisk'],
        ]);

        $this->assertTrue($form->isValid(), (string) $form->getErrors(true));
        $this->assertSame(['Fakturering', 'Teknisk'], $this->roleNames($contact));
    }

    public function testEmptySubmitLeavesTheContactBlank(): void
    {
        $contact = new ServiceAgreementContact();
        $form = $this->createForm(ServiceAgreementContactType::class, $contact);

        $form->submit(['name' => '', 'email' => '', 'roles' => []]);

        $this->assertTrue($form->isSynchronized());
        $this->assertNull($contact->getName());
        $this->assertNull($contact->getEmail());
        $this->assertCount(0, $contact->getRoles());
    }

    public function testMalformedEmailIsRejected(): void
    {
        $form = $this->createForm(ServiceAgreementContactType::class, new ServiceAgreementContact());

        $form->submit(['name' => 'Dan Poulsen', 'email' => 'not-an-email']);

        $this->assertFalse($form->isValid());
        $this->assertGreaterThan(0, $form->get('email')->getErrors()->count());
    }

    private function persistRole(string $name): ContactRole
    {
        $role = new ContactRole();
        $role->setName($name);

        // The surrounding transaction rolls this back; see AbstractFormTestCase.
        $this->entityManager->persist($role);
        $this->entityManager->flush();

        return $role;
    }

    /**
     * @return string[]
     */
    private function roleNames(ServiceAgreementContact $contact): array
    {
        return $contact->getRoles()
            ->map(fn (ContactRole $role) => (string) $role->getName())
            ->toArray();
    }
}
