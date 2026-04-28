<?php

namespace App\Form;

use App\Model\Invoices\ProjectBillingFilterData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProjectBillingFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('recorded', ChoiceType::class, [
                'required' => true,
                'label' => 'project_billing.form_recorded',
                'label_attr' => ['class' => 'label'],
                'choices' => [
                    'invoices.recorded_false' => false,
                    'invoices.recorded_true' => true,
                ],
                'attr' => ['class' => 'form-element'],
            ])
            ->add('createdBy', SearchType::class, [
                'required' => false,
                'label' => 'project_billing.form_created_by',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'data_class' => ProjectBillingFilterData::class,
        ]);
    }
}
