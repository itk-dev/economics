<?php

namespace App\Form;

use App\Entity\IssueProduct;
use App\Entity\Product;
use App\Entity\Project;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

class IssueProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $quantityMin = $options['quantity_min'] ?? 0.1;
        $quantityStep = $options['quantity_step'] ?? $quantityMin;
        $quantityScale = (int) ceil(-log10($quantityStep));

        $builder
            ->add('product', EntityType::class, [
                'placeholder' => new TranslatableMessage('issue.placeholder_select_product'),
                'class' => Product::class,
                'choice_label' => 'name',
                'query_builder' => function (EntityRepository $er) use ($options): QueryBuilder {
                    return $er->createQueryBuilder('p')
                        ->andWhere('p.project = :project')
                        ->setParameter('project', $options['project'])
                        ->orderBy('p.name', Criteria::ASC);
                },
                'row_attr' => ['class' => 'form-row form-choices'],
                'attr' => [
                    'class' => 'form-element',
                    'data-choices-target' => 'choices',
                ],
            ])
            ->add('quantity', NumberType::class, [
                'html5' => true,
                'scale' => $quantityScale,
                'attr' => [
                    'min' => $quantityMin,
                    'step' => $quantityStep,
                    'class' => 'number text-right',
                    'size' => 4,
                ],
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
            ])
            ->add('submit', SubmitType::class, [
                'label' => $builder->getData()?->getId()
                    ? new TranslatableMessage('issue.product.action_update')
                    : new TranslatableMessage('issue.product.action_add'),
                'attr' => [
                    'class' => 'button',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'data_class' => IssueProduct::class,
            ])
            ->setRequired('project')
            ->setAllowedTypes('project', Project::class);
    }
}
