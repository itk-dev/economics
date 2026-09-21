<?php

namespace App\Tests\Integration\Form;

use App\Entity\Issue;
use App\Entity\IssueProduct;
use App\Entity\Product;
use App\Entity\Project;
use App\Form\IssueProductType;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;

class IssueProductTypeTest extends AbstractFormTestCase
{
    private function project(): Project
    {
        return $this->requireEntity(Project::class, $this->findOne(Product::class)->getProject());
    }

    private function persistIssueProduct(): IssueProduct
    {
        $project = $this->project();

        $issueProduct = new IssueProduct();
        $issueProduct->setIssue($this->findOne(Issue::class, ['project' => $project]));
        $issueProduct->setProduct($this->findOne(Product::class, ['project' => $project]));
        $issueProduct->setQuantity(1.0);

        $this->entityManager->persist($issueProduct);
        $this->entityManager->flush();

        return $issueProduct;
    }

    public function testFormExposesExpectedFields(): void
    {
        $this->assertHasFields(
            IssueProductType::class,
            ['product', 'quantity', 'description', 'submit'],
            null,
            ['project' => $this->project()]
        );
    }

    public function testDataClassIsIssueProduct(): void
    {
        $form = $this->createForm(IssueProductType::class, null, ['project' => $this->project()]);

        $this->assertSame(IssueProduct::class, $form->getConfig()->getOption('data_class'));
    }

    public function testProjectOptionIsRequired(): void
    {
        $this->expectException(MissingOptionsException::class);

        $this->createForm(IssueProductType::class);
    }

    public function testProjectOptionMustBeAProject(): void
    {
        $this->expectException(InvalidOptionsException::class);

        $this->createForm(IssueProductType::class, null, ['project' => 'not-a-project']);
    }

    public function testQuantityScaleMustBeAnInteger(): void
    {
        $this->expectException(InvalidOptionsException::class);

        $this->createForm(IssueProductType::class, null, ['project' => $this->project(), 'quantity_scale' => '2']);
    }

    public function testQuantityScaleDefaultsToTwo(): void
    {
        $form = $this->createForm(IssueProductType::class, null, ['project' => $this->project()]);

        $this->assertSame(2, $form->get('quantity')->getConfig()->getOption('scale'));
    }

    public function testQuantityScaleIsConfigurable(): void
    {
        $form = $this->createForm(IssueProductType::class, null, ['project' => $this->project(), 'quantity_scale' => 4]);

        $this->assertSame(4, $form->get('quantity')->getConfig()->getOption('scale'));
    }

    public function testProductChoicesAreLimitedToTheGivenProject(): void
    {
        $project = $this->project();
        $form = $this->createForm(IssueProductType::class, null, ['project' => $project]);

        $choices = $form->createView()->children['product']->vars['choices'];
        $this->assertNotEmpty($choices);

        foreach ($choices as $choice) {
            $this->assertSame($project->getId(), $choice->data->getProject()->getId());
        }
    }

    public function testProductChoicesAreOrderedByName(): void
    {
        $form = $this->createForm(IssueProductType::class, null, ['project' => $this->project()]);

        $names = array_map(
            fn ($choice) => $choice->data->getName(),
            $form->createView()->children['product']->vars['choices']
        );

        $names = array_values($names);
        $sorted = $names;
        sort($sorted);
        $this->assertSame($sorted, $names);
    }

    public function testSubmitLabelIsAddForNewIssueProducts(): void
    {
        $form = $this->createForm(IssueProductType::class, new IssueProduct(), ['project' => $this->project()]);

        $this->assertSame(
            'issue.product.action_add',
            $form->get('submit')->getConfig()->getOption('label')->getMessage()
        );
    }

    public function testSubmitLabelIsUpdateForPersistedIssueProducts(): void
    {
        $issueProduct = $this->persistIssueProduct();

        $form = $this->createForm(IssueProductType::class, $issueProduct, ['project' => $this->project()]);

        $this->assertSame(
            'issue.product.action_update',
            $form->get('submit')->getConfig()->getOption('label')->getMessage()
        );
    }

    public function testSubmitMapsDataToIssueProduct(): void
    {
        $project = $this->project();
        $product = $this->findOne(Product::class, ['project' => $project]);
        $productId = $this->requireId($product->getId());

        $issueProduct = new IssueProduct();
        $form = $this->createForm(IssueProductType::class, $issueProduct, ['project' => $project]);

        $form->submit([
            'product' => (string) $productId,
            'quantity' => '3.5',
            'description' => 'Three and a half units',
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid(), (string) $form->getErrors(true));
        $this->assertSame($productId, $this->requireEntity(Product::class, $issueProduct->getProduct())->getId());
        $this->assertSame(3.5, $issueProduct->getQuantity());
        $this->assertSame('Three and a half units', $issueProduct->getDescription());
    }

    public function testDescriptionIsOptional(): void
    {
        $form = $this->createForm(IssueProductType::class, new IssueProduct(), ['project' => $this->project()]);

        $this->assertFalse($form->get('description')->isRequired());
    }
}
