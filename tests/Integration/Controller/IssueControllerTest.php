<?php

namespace App\Tests\Integration\Controller;

use App\Entity\Issue;
use App\Entity\IssueProduct;
use App\Entity\Product;
use App\Entity\Project;
use App\Enum\IssueStatusEnum;
use Symfony\Component\DomCrawler\Crawler;

class IssueControllerTest extends AbstractTransactionalFlowTestCase
{
    private int $projectId;
    private int $issueId;
    private int $productId;

    protected function setUp(): void
    {
        $this->bootTransactionalClient('ROLE_PRODUCT_MANAGER');

        $product = $this->findOne(Product::class);
        $this->productId = $this->requireId($product->getId());
        $this->projectId = $this->requireId($this->requireProject($product)->getId());

        $issue = new Issue();
        $issue->setName('Issue under test');
        $issue->setStatus(IssueStatusEnum::NEW);
        $issue->setProject($this->requireProject($product));
        $issue->setProjectTrackerId('issue-under-test');
        $issue->setProjectTrackerKey('ECON-TEST');
        $issue->setLinkToIssue('https://tracker.example/issues/test');
        $issue->setPlanHours(null);
        $issue->setHoursRemaining(null);
        $this->entityManager->persist($issue);
        $this->entityManager->flush();
        $this->issueId = $this->requireId($issue->getId());
    }

    private function requireProject(Product $product): Project
    {
        $project = $product->getProject();
        $this->assertInstanceOf(Project::class, $project, 'Fixture products must belong to a project.');

        return $project;
    }

    public function testIndexIsReachableForProductManagers(): void
    {
        $this->client->request('GET', $this->indexUrl());

        $this->assertResponseIsSuccessful();
    }

    public function testIndexIsDeniedForOtherRoles(): void
    {
        $this->assertDeniedFor($this->indexUrl(), ['ROLE_INVOICE']);
    }

    public function testIndexFilterKeepsMatchingIssues(): void
    {
        $crawler = $this->submitFilter('Issue under test');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Issue under test', $crawler->html());
    }

    public function testIndexFilterExcludesNonMatchingIssues(): void
    {
        $crawler = $this->submitFilter('no-issue-matches-this-name');

        $this->assertResponseIsSuccessful();
        $this->assertStringNotContainsString('Issue under test', $crawler->html());
    }

    public function testShowRendersTheIssue(): void
    {
        $crawler = $this->client->request('GET', $this->showUrl());

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Issue under test', $crawler->html());
    }

    public function testShowOffersAnAddProductFormWhenTheProjectHasProducts(): void
    {
        $crawler = $this->client->request('GET', $this->showUrl());

        $this->assertResponseIsSuccessful();
        $this->assertGreaterThan(0, $crawler->filter('form[name="issue_product"]')->count());
    }

    public function testShowOmitsTheAddProductFormForProjectsWithoutProducts(): void
    {
        [$projectId, $issueId] = $this->persistProjectWithoutProductsAndIssue();

        $crawler = $this->client->request('GET', sprintf('/admin/project/%d/issues/%d', $projectId, $issueId));

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('form[name="issue_product"]'));
    }

    public function testAddProductAttachesTheProductToTheIssue(): void
    {
        $crawler = $this->client->request('GET', $this->showUrl());
        $form = $crawler->filter('form[name="issue_product"]')->form();
        $form['issue_product[product]'] = (string) $this->productId;
        $form['issue_product[quantity]'] = '3';
        $form['issue_product[description]'] = 'Three units';

        $this->client->submit($form);

        $this->assertResponseRedirects($this->showUrl());

        $products = $this->reloadIssueProducts();
        $this->assertCount(1, $products);
        $this->assertSame(3.0, $products[0]->getQuantity());
        $this->assertSame('Three units', $products[0]->getDescription());
    }

    public function testAddProductWithAnUnparsableQuantityAddsNothing(): void
    {
        $crawler = $this->client->request('GET', $this->showUrl());
        $form = $crawler->filter('form[name="issue_product"]')->form();
        $form['issue_product[product]'] = (string) $this->productId;
        $form['issue_product[quantity]'] = 'not-a-number';

        $this->client->submit($form);

        $this->assertResponseRedirects($this->showUrl());
        $this->assertCount(0, $this->reloadIssueProducts());
    }

    public function testShowRendersAnEditFormPerAttachedProduct(): void
    {
        $issueProductId = $this->persistIssueProduct(2.0);

        $crawler = $this->client->request('GET', $this->showUrl());

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter(sprintf('form[action$="/editProduct/%d"]', $issueProductId)));
    }

    public function testEditProductUpdatesQuantityAndDescription(): void
    {
        $issueProductId = $this->persistIssueProduct(2.0);

        $crawler = $this->client->request('GET', $this->showUrl());
        $form = $crawler->filter(sprintf('form[action$="/editProduct/%d"]', $issueProductId))->form();
        $form['issue_product[product]'] = (string) $this->productId;
        $form['issue_product[quantity]'] = '7.5';
        $form['issue_product[description]'] = 'Seven and a half';

        $this->client->submit($form);

        $this->assertResponseRedirects($this->showUrl());

        $reloaded = $this->reloadIssueProduct($issueProductId);
        $this->assertNotNull($reloaded);
        $this->assertSame(7.5, $reloaded->getQuantity());
        $this->assertSame('Seven and a half', $reloaded->getDescription());
    }

    public function testEditProductWithAnUnparsableQuantityKeepsTheStoredValue(): void
    {
        $issueProductId = $this->persistIssueProduct(2.0);

        $crawler = $this->client->request('GET', $this->showUrl());
        $form = $crawler->filter(sprintf('form[action$="/editProduct/%d"]', $issueProductId))->form();
        $form['issue_product[product]'] = (string) $this->productId;
        $form['issue_product[quantity]'] = 'not-a-number';

        $this->client->submit($form);

        $this->assertResponseRedirects($this->showUrl());

        $reloaded = $this->reloadIssueProduct($issueProductId);
        $this->assertNotNull($reloaded);
        $this->assertSame(2.0, $reloaded->getQuantity());
        $this->assertNotNull($reloaded->getProduct());
        $this->assertSame($this->productId, $reloaded->getProduct()->getId());
    }

    public function testDeleteProductRemovesItWithAValidToken(): void
    {
        $issueProductId = $this->persistIssueProduct(1.0);

        $crawler = $this->client->request('GET', $this->showUrl());
        $deleteForm = $crawler->filter(sprintf('form[action$="/deleteProduct/%d"]', $issueProductId))->form();

        $this->client->submit($deleteForm);

        $this->assertResponseRedirects($this->showUrl());
        $this->assertNull($this->reloadIssueProduct($issueProductId));
    }

    public function testDeleteProductIsIgnoredWithAnInvalidToken(): void
    {
        $issueProductId = $this->persistIssueProduct(1.0);

        $this->client->request('DELETE', sprintf('%s/deleteProduct/%d', $this->showUrl(), $issueProductId), [
            '_token' => 'invalid-token',
        ]);

        $this->assertResponseRedirects($this->showUrl());
        $this->assertNotNull($this->reloadIssueProduct($issueProductId));
    }

    public function testShowSumsUpTheAttachedProducts(): void
    {
        $this->persistIssueProduct(2.0);
        $this->persistIssueProduct(3.0);

        $crawler = $this->client->request('GET', $this->showUrl());

        $this->assertResponseIsSuccessful();
        $this->assertCount(2, $crawler->filter('form[action*="/editProduct/"]'));
    }

    private function submitFilter(string $name): Crawler
    {
        $crawler = $this->client->request('GET', $this->indexUrl());
        $form = $crawler->filter('form[name="issue_filter"]')->form();
        $form['issue_filter[name]'] = $name;

        return $this->client->submit($form);
    }

    private function indexUrl(): string
    {
        return sprintf('/admin/project/%d/issues/', $this->projectId);
    }

    private function showUrl(): string
    {
        return sprintf('/admin/project/%d/issues/%d', $this->projectId, $this->issueId);
    }

    private function persistIssueProduct(float $quantity): int
    {
        $issueProduct = new IssueProduct();
        $issueProduct->setIssue($this->entityManager->getRepository(Issue::class)->find($this->issueId));
        $issueProduct->setProduct($this->entityManager->getRepository(Product::class)->find($this->productId));
        $issueProduct->setQuantity($quantity);
        $this->entityManager->persist($issueProduct);
        $this->entityManager->flush();
        $id = $this->requireId($issueProduct->getId());

        // The kernel shares this entity manager, so the controller would
        // otherwise render a stale, already-initialised products collection.
        $this->entityManager->clear();

        return $id;
    }

    /**
     * @return IssueProduct[]
     */
    private function reloadIssueProducts(): array
    {
        $this->entityManager->clear();

        return $this->entityManager->getRepository(IssueProduct::class)->findBy(['issue' => $this->issueId]);
    }

    private function reloadIssueProduct(int $id): ?IssueProduct
    {
        $this->entityManager->clear();

        return $this->findByIdOrNull(IssueProduct::class, $id);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function persistProjectWithoutProductsAndIssue(): array
    {
        $project = new Project();
        $project->setName('Project without products');
        $project->setProjectTrackerId('project-without-products');
        $project->setProjectTrackerKey('PWP');
        $project->setProjectTrackerProjectUrl('http://localhost/');
        $project->setInclude(false);
        $this->entityManager->persist($project);

        $issue = new Issue();
        $issue->setName('Issue without products');
        $issue->setStatus(IssueStatusEnum::NEW);
        $issue->setProject($project);
        $issue->setProjectTrackerId('issue-without-products');
        $issue->setProjectTrackerKey('PWP-1');
        $issue->setLinkToIssue('https://tracker.example/issues/empty');
        $issue->setPlanHours(null);
        $issue->setHoursRemaining(null);
        $this->entityManager->persist($issue);

        $this->entityManager->flush();
        $ids = [$this->requireId($project->getId()), $this->requireId($issue->getId())];
        $this->entityManager->clear();

        return $ids;
    }
}
