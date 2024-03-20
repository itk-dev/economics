<?php

namespace App\Form;

use App\Entity\View;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class ViewSelectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('viewSelect', EntityType::class, [
                'class' => View::class,
                'label' => false,
                'choice_label' => 'name',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'form-element',
                    'data-action' => 'view-selector#select',
                ],
                'data' => $options['data'] ?? null,
            ]);
    }
}
