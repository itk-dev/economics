<?php

namespace App\Form\Invoices;

use App\Entity\Invoice;
use App\Entity\ProjectBilling;
use App\Enum\MaterialNumberEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProjectBillingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, [
                'required' => true,
                'label' => 'project_billing.name',
                'label_attr' => ['class' => 'label'],
                'row_attr' => ['class' => 'form-row'],
                'attr' => ['class' => 'form-element'],
                'help' => 'project_billing.name_helptext',
            ])
            ->add('periodStart', DateTimeType::class, [
                'required' => false,
                'label' => 'project_billing.period_start',
                'label_attr' => ['class' => 'label'],
                'row_attr' => ['class' => 'form-row'],
                'help' => 'project_billing.period_start_helptext',
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['class' => 'form-element'],
            ])
            ->add('periodEnd', DateTimeType::class, [
                'required' => false,
                'label' => 'project_billing.period_end',
                'label_attr' => ['class' => 'label'],
                'row_attr' => ['class' => 'form-row'],
                'attr' => ['class' => 'form-element'],
                'help' => 'project_billing.period_end_helptext',
                'widget' => 'single_text',
                'html5' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProjectBilling::class,
        ]);
    }
}
