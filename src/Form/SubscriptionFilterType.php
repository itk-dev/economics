<?php

namespace App\Form;

use App\Model\Invoices\SubscriptionFilterData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SubscriptionFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('urlParams', SearchType::class, [
                'required' => false,
                'label' => 'subscription.urlParams_search',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'data_class' => SubscriptionFilterData::class,
        ]);
    }
}
