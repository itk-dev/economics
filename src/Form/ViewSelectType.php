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
                'required' => true,
                'mapped' => false,
                'attr' => [
                    'class' => 'form-element',
                    'data-action' => 'view-selector#select',
                    'data-default-view-update-endpoint' => '/admin/1/view/set-default',
                ],
                'data' => $options['data'] ?? null,
            ]);
    }
}
