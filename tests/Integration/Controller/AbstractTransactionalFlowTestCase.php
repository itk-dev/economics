<?php

namespace App\Tests\Integration\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\DomCrawler\Field\FormField;
use Symfony\Component\DomCrawler\Form;

/**
 * Base for flow tests that write to the database.
 *
 * The fixtures are loaded once for the whole suite, so every request runs inside
 * a transaction that is rolled back afterwards. Keeping one kernel alive is what
 * lets a single transaction span several requests.
 */
abstract class AbstractTransactionalFlowTestCase extends AbstractControllerTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $entityManager;

    private string $bootedRole;

    protected function bootTransactionalClient(string $role): void
    {
        $this->bootedRole = $role;
        $this->client = $this->createClientLoggedInAs([$role]);
        $this->client->disableReboot();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $this->entityManager = $entityManager;

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
     * Asserts that $role is denied access to $url.
     *
     * The inherited assertDeniedFor() boots a fresh client, which would shut down
     * the kernel holding this test's open transaction. Switching user on the live
     * client keeps that transaction intact.
     */
    protected function assertDeniedForRole(string $url, string $role): void
    {
        $this->client->loginUser($this->getUserWithRole($role));
        $this->client->request('GET', $url);

        $this->assertResponseStatusCodeSame(403, sprintf('Expected 403 at %s for role %s', $url, $role));

        $this->client->loginUser($this->getUserWithRole($this->bootedRole));
    }

    /**
     * Submits the named form found on $url after applying $values.
     *
     * Going through the rendered form keeps CSRF tokens and field names honest.
     *
     * @param array<string, string|string[]> $values
     */
    protected function submitFormAt(string $url, string $formName, array $values): void
    {
        $crawler = $this->client->request('GET', $url);
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter(sprintf('form[name="%s"]', $formName))->form();
        foreach ($values as $field => $value) {
            $form[$field] = $value;
        }

        $this->client->submit($form);
    }

    /**
     * Submits the delete form rendered for $url, which carries a valid CSRF token.
     */
    protected function submitDeleteFormAt(string $pageUrl, string $deleteAction): void
    {
        $crawler = $this->client->request('GET', $pageUrl);
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter(sprintf('form[action$="%s"]', $deleteAction))->form();
        $this->client->submit($form);
    }

    /**
     * Fetch a single entity, failing the test when absent.
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
     * Fetch an entity by id, failing the test when absent.
     *
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return T
     */
    protected function findById(string $className, int $id): object
    {
        $entity = $this->entityManager->getRepository($className)->find($id);
        $this->assertInstanceOf($className, $entity, sprintf('Expected %s #%d to exist.', $className, $id));

        return $entity;
    }

    /**
     * Fetch an entity by id without asserting it still exists.
     *
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return T|null
     */
    protected function findByIdOrNull(string $className, int $id): ?object
    {
        return $this->entityManager->getRepository($className)->find($id);
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

    protected function requireString(?string $value): string
    {
        $this->assertNotNull($value, 'Expected a non-null string.');

        return $value;
    }

    protected function responseContent(): string
    {
        $content = $this->client->getResponse()->getContent();
        $this->assertIsString($content, 'Expected a buffered response body.');

        return $content;
    }

    /**
     * @return array<mixed>
     */
    protected function responseJson(): array
    {
        $decoded = json_decode($this->responseContent(), true);
        $this->assertIsArray($decoded, 'Expected a JSON response body.');

        return $decoded;
    }

    /**
     * @param array<mixed> $payload
     */
    protected function requestJson(string $method, string $url, array $payload): void
    {
        $this->client->request(
            $method,
            $url,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode($payload)
        );
    }

    protected function choiceField(Form $form, string $name): ChoiceFormField
    {
        $field = $form[$name];
        $this->assertInstanceOf(ChoiceFormField::class, $field, sprintf('Field "%s" is not a choice field.', $name));

        return $field;
    }

    protected function fieldValue(Form $form, string $name): string
    {
        $field = $form[$name];
        $this->assertInstanceOf(FormField::class, $field, sprintf('Field "%s" is not a single form field.', $name));

        $value = $field->getValue();
        $this->assertIsString($value, sprintf('Field "%s" does not hold a single value.', $name));

        return $value;
    }
}
