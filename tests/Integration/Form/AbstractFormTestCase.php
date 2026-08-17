<?php

namespace App\Tests\Integration\Form;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

abstract class AbstractFormTestCase extends KernelTestCase
{
    protected FormFactoryInterface $formFactory;
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $formFactory = $container->get(FormFactoryInterface::class);
        \assert($formFactory instanceof FormFactoryInterface);
        $this->formFactory = $formFactory;

        $entityManager = $container->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $this->entityManager = $entityManager;

        // The fixtures are loaded once for the whole suite, so anything these
        // tests persist is rolled back to keep the shared database untouched.
        $this->entityManager->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->entityManager)) {
            $connection = $this->entityManager->getConnection();
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }

        parent::tearDown();
    }

    /**
     * CSRF protection is disabled so tests can submit raw payloads without a token.
     *
     * @param class-string<\Symfony\Component\Form\FormTypeInterface<mixed>> $type
     * @param array<string, mixed>                                           $options
     *
     * @return FormInterface<mixed>
     */
    protected function createForm(string $type, mixed $data = null, array $options = []): FormInterface
    {
        return $this->formFactory->create($type, $data, $options + ['csrf_protection' => false]);
    }

    /**
     * Resolve the submit value a choice field expects for a given choice.
     *
     * Fields without an explicit `choice_value` fall back to positional values,
     * so reading them off the view keeps tests independent of that detail.
     *
     * @param FormInterface<mixed> $form
     */
    protected function choiceValue(FormInterface $form, string $field, mixed $choice): string
    {
        foreach ($form->createView()->children[$field]->vars['choices'] as $choiceView) {
            if ($choiceView->data === $choice) {
                return (string) $choiceView->value;
            }
        }

        throw new \InvalidArgumentException(sprintf('Field "%s" has no choice for the given value.', $field));
    }

    /**
     * Fetch a single entity from the fixtures, failing the test when absent.
     *
     * @template T of object
     *
     * @param class-string<T>      $className
     * @param array<string, mixed> $criteria
     *
     * @return T
     */
    protected function findOne(string $className, array $criteria = []): object
    {
        $entity = $this->entityManager->getRepository($className)->findOneBy($criteria);
        $this->assertInstanceOf($className, $entity, sprintf('Expected a %s in the database.', $className));

        return $entity;
    }

    /**
     * Narrow a nullable relation to a concrete entity, failing the test when absent.
     *
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return T
     */
    protected function requireEntity(string $className, ?object $entity): object
    {
        $this->assertInstanceOf($className, $entity, sprintf('Expected a %s.', $className));

        return $entity;
    }

    protected function requireId(?int $id): int
    {
        $this->assertNotNull($id, 'Expected a persisted entity with an id.');

        return $id;
    }

    /**
     * @param class-string<\Symfony\Component\Form\FormTypeInterface<mixed>> $type
     * @param string[]                                                       $expectedFields
     * @param array<string, mixed>                                           $options
     */
    protected function assertHasFields(string $type, array $expectedFields, mixed $data = null, array $options = []): void
    {
        $form = $this->createForm($type, $data, $options);

        foreach ($expectedFields as $field) {
            $this->assertTrue($form->has($field), sprintf('Form %s is missing field "%s".', $type, $field));
        }

        $this->assertSame(
            $expectedFields,
            array_keys($form->all()),
            sprintf('Form %s does not expose exactly the expected fields.', $type)
        );
    }
}
