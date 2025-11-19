<?php

namespace App\Form;

use App\Entity\CybersecurityAgreement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CybersecurityAgreementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('quarterlyHours', NumberType::class, [
                'label' => 'service_agreement.quarterly_hours',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row'],
                'required' => false,
            ])
            ->add('note', TextareaType::class, [
                'label' => 'service_agreement.note',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element', 'style' => 'height: 400px;'],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row'],
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CybersecurityAgreement::class,
        ]);
    }
}
