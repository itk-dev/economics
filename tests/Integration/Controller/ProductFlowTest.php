<?php

namespace App\Tests\Integration\Controller;

use App\Entity\Product;
use App\Entity\Project;

class ProductFlowTest extends AbstractTransactionalFlowTestCase
{
    private int $projectId;

    protected function setUp(): void
    {
        $this->bootTransactionalClient('ROLE_PRODUCT_MANAGER');

        $this->projectId = $this->requireId(
            $this->requireEntity(Project::class, $this->findOne(Product::class)->getProject())->getId()
        );
    }

    public function testIndexListsProducts(): void
    {
        $crawler = $this->client->request('GET', '/admin/products/');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Product Alpha', $crawler->html());
    }

    public function testIndexScopesToTheProjectQueryParameter(): void
    {
        $otherProject = $this->persistProject('Project without products');

        $crawler = $this->client->request('GET', '/admin/products/?project='.$otherProject);

        $this->assertResponseIsSuccessful();
        $this->assertStringNotContainsString('Product Alpha', $crawler->html());
    }

    public function testNewFormIsRendered(): void
    {
        $this->client->request('GET', '/admin/products/new');

        $this->assertResponseIsSuccessful();
    }

    public function testNewPreselectsTheProjectFromTheQueryParameter(): void
    {
        $crawler = $this->client->request('GET', '/admin/products/new?project='.$this->projectId);

        $this->assertResponseIsSuccessful();
        $this->assertSame(
            (string) $this->projectId,
            $this->fieldValue($crawler->filter('form[name="product"]')->form(), 'product[project]')
        );
    }

    public function testNewCreatesAProduct(): void
    {
        $this->submitFormAt('/admin/products/new', 'product', [
            'product[project]' => (string) $this->projectId,
            'product[name]' => 'Product Created By Test',
            'product[price]' => '123.45',
        ]);

        $this->assertResponseRedirects('/admin/products/');

        $this->entityManager->clear();
        $product = $this->findOne(Product::class, ['name' => 'Product Created By Test']);
        $this->assertSame('123.45', $product->getPrice());
    }

    public function testNewRejectsANegativePrice(): void
    {
        $this->submitFormAt('/admin/products/new', 'product', [
            'product[project]' => (string) $this->projectId,
            'product[name]' => 'Negative Product',
            'product[price]' => '-1',
        ]);

        $this->assertResponseStatusCodeSame(422);

        $this->entityManager->clear();
        $this->assertNull(
            $this->entityManager->getRepository(Product::class)->findOneBy(['name' => 'Negative Product'])
        );
    }

    public function testShowRendersTheProduct(): void
    {
        $id = $this->persistProduct('Product To Show', '10.00');

        $crawler = $this->client->request('GET', '/admin/products/'.$id);

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Product To Show', $crawler->html());
    }

    public function testEditUpdatesTheProduct(): void
    {
        $id = $this->persistProduct('Product To Edit', '10.00');

        $this->submitFormAt(sprintf('/admin/products/%d/edit', $id), 'product', [
            'product[project]' => (string) $this->projectId,
            'product[name]' => 'Product Edited',
            'product[price]' => '77.70',
        ]);

        $this->assertResponseRedirects('/admin/products/');

        $this->entityManager->clear();
        $product = $this->findById(Product::class, $id);
        $this->assertSame('Product Edited', $product->getName());
        $this->assertSame('77.70', $product->getPrice());
    }

    public function testDeleteRemovesTheProduct(): void
    {
        $id = $this->persistProduct('Product To Delete', '10.00');

        $this->submitDeleteFormAt(sprintf('/admin/products/%d/edit', $id), '/admin/products/'.$id);

        $this->assertResponseRedirects('/admin/products/');

        $this->entityManager->clear();
        $this->assertNull($this->findByIdOrNull(Product::class, $id));
    }

    public function testDeleteWithAnInvalidTokenKeepsTheProduct(): void
    {
        $id = $this->persistProduct('Product To Keep', '10.00');

        $this->client->request('POST', '/admin/products/'.$id, ['_token' => 'invalid-token']);

        $this->assertResponseRedirects('/admin/products/');

        $this->entityManager->clear();
        $this->assertNotNull($this->findByIdOrNull(Product::class, $id));
    }

    private function persistProduct(string $name, string $price): int
    {
        $product = new Product();
        $product->setName($name);
        $product->setPrice($price);
        $product->setProject($this->entityManager->getRepository(Project::class)->find($this->projectId));
        $this->entityManager->persist($product);
        $this->entityManager->flush();
        $id = $this->requireId($product->getId());
        $this->entityManager->clear();

        return $id;
    }

    private function persistProject(string $name): int
    {
        $project = new Project();
        $project->setName($name);
        $project->setProjectTrackerId('flow-'.uniqid());
        $project->setProjectTrackerKey('FLOW');
        $project->setProjectTrackerProjectUrl('http://localhost/');
        $project->setInclude(false);
        $this->entityManager->persist($project);
        $this->entityManager->flush();
        $id = $this->requireId($project->getId());
        $this->entityManager->clear();

        return $id;
    }
}
