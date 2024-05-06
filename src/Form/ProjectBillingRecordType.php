<?php

namespace App\Form;

use App\Model\Invoices\ConfirmData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProjectBillingRecordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('confirmation', ChoiceType::class, [
                'required' => true,
                'label' => 'project_billing.record_project_billing',
                'label_attr' => ['class' => 'label'],
                'choices' => [
                    'project_billing.record_project_billing_false' => false,
                    'project_billing.record_project_billing_true' => true,
                ],
                'help' => 'project_billing.record_project_billing_helptext',
                'attr' => ['class' => 'form-element'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'data_class' => ConfirmData::class,
        ]);
    }
}
