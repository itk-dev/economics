<?php

namespace App\Form;

use App\Entity\Invoice;
use App\Entity\Project;
use App\Repository\ProjectRepository;
use Symfony\Component\Form\AbstractType;
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
            ->add('project', null, [
                'required' => true,
                'query_builder' => function (ProjectRepository $projectRepository) {
                    return $projectRepository->getIncluded();
                },
                'attr' => ['class' => 'form-element'],
                'choice_label' => function (Project $pr) {
                    return $pr->getName().' ('.$pr->getDataProvider().')';
                },
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
