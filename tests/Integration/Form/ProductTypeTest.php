<?php

namespace App\Tests\Integration\Form;

use App\Entity\Product;
use App\Entity\Project;
use App\Form\ProductType;

class ProductTypeTest extends AbstractFormTestCase
{
    public function testFormExposesExpectedFields(): void
    {
        $this->assertHasFields(ProductType::class, ['project', 'name', 'price']);
    }

    public function testDataClassIsProduct(): void
    {
        $form = $this->createForm(ProductType::class);

        $this->assertSame(Product::class, $form->getConfig()->getOption('data_class'));
    }

    public function testAllFieldsAreRequired(): void
    {
        $form = $this->createForm(ProductType::class, new Product());

        foreach (['project', 'name', 'price'] as $field) {
            $this->assertTrue($form->get($field)->isRequired(), sprintf('Field "%s" should be required.', $field));
        }
    }

    public function testProjectFieldIsAProjectChoiceWithPlaceholder(): void
    {
        $config = $this->createForm(ProductType::class)->get('project')->getConfig();

        $this->assertSame(Project::class, $config->getOption('class'));
        $this->assertSame('name', $config->getOption('choice_label'));
        $this->assertNotNull($config->getOption('placeholder'));
    }

    public function testPriceUsesTwoDecimals(): void
    {
        $config = $this->createForm(ProductType::class)->get('price')->getConfig();

        $this->assertSame(2, $config->getOption('scale'));
        $this->assertTrue($config->getOption('html5'));
    }

    public function testSubmitMapsDataToProduct(): void
    {
        $project = $this->findOne(Project::class);
        $projectId = $this->requireId($project->getId());

        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);

        $form->submit([
            'project' => (string) $projectId,
            'name' => 'Product Delta',
            'price' => '199.95',
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid(), (string) $form->getErrors(true));
        $this->assertSame($projectId, $this->requireEntity(Project::class, $product->getProject())->getId());
        $this->assertSame('Product Delta', $product->getName());
        $this->assertSame('199.95', $product->getPrice());
        $this->assertSame(199.95, $product->getPriceAsFloat());
    }

    public function testNegativePriceIsRejected(): void
    {
        $form = $this->createForm(ProductType::class, new Product());

        $form->submit([
            'project' => (string) $this->requireId($this->findOne(Project::class)->getId()),
            'name' => 'Negative Product',
            'price' => '-1',
        ]);

        $this->assertFalse($form->isValid());
    }

    public function testBlankNameIsRejected(): void
    {
        $form = $this->createForm(ProductType::class, new Product());

        $form->submit([
            'project' => (string) $this->requireId($this->findOne(Project::class)->getId()),
            'name' => '',
            'price' => '10',
        ]);

        $this->assertFalse($form->isValid());
    }

    public function testUnknownProjectIsRejected(): void
    {
        $form = $this->createForm(ProductType::class, new Product());

        $form->submit([
            'project' => '999999',
            'name' => 'Orphan Product',
            'price' => '10',
        ]);

        $this->assertFalse($form->isValid());
    }
}
