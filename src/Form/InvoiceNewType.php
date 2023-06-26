<?php

namespace App\Form;

use App\Entity\Invoice;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InvoiceNewType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, [
                'required' => true,
                'attr' => ['class' => 'form-element'],
            ])
            ->add('description', TextareaType::class, [
                'required' => true,
                'attr' => ['class' => 'form-element', 'rows' => 4],
            ])
            ->add('project', null, [
                'required' => true,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('p')
                        ->where('p.include IS NOT NULL')
                        ->orderBy('p.name', 'ASC');
                },
                'attr' => ['class' => 'form-element'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Invoice::class,
        ]);
    }
}