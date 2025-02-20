<?php

namespace App\Form;

use App\Model\Planning\PlanningFormData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlanningType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        [$currentYear, $nextYear] = $options['years'];

        $builder
            ->add('year', ChoiceType::class, [
                'label' => 'planning.year',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element ', 'onchange' => 'this.form.submit()'],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row'],
                'required' => false,
                'empty_data' => $currentYear,
                'choices' => [$currentYear => $currentYear, $nextYear => $nextYear],
                'placeholder' => null,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PlanningFormData::class,
            'attr' => [
                'class' => 'form-default',
            ],
            'years' => null,
        ]);
    }
}
