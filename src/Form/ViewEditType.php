<?php

namespace App\Form;

use App\Entity\Project;
use App\Entity\View;
use App\Service\ViewService;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ViewEditType extends AbstractType
{
    public function __construct(protected readonly ViewService $viewService)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('projects', EntityType::class, [
                'class' => Project::class,
                'choice_label' => 'name',
                'multiple' => true,
                'attr' => ['class' => 'form-element'],
                'label' => 'view.projects',
                'required' => false,
            ])
            ->add('workers', ChoiceType::class, [
                'choices' => $this->viewService->getWorklogUsers(),
                'mapped' => false,
                'multiple' => true,
                'attr' => ['class' => 'form-element'],
                'label' => 'view.users',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => View::class,
        ]);
    }
}
