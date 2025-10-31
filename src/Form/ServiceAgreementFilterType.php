<?php

namespace App\Form;

use App\Enum\HostingProviderEnum;
use App\Model\Invoices\ServiceAgreementFilterData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ServiceAgreementFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('project', SearchType::class, [
                'required' => false,
                'label' => 'service_agreement.project',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
            ])
            ->add('client', SearchType::class, [
                'required' => false,
                'label' => 'service_agreement.client',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
            ])
            ->add('cybersecurityAgreement', ChoiceType::class, [
                'required' => false,
                'label' => 'service_agreement.cyber_security_agreement.filter_label',
                'label_attr' => ['class' => 'label'],
                'choices' => [
                    'service_agreement.yes' => true,
                    'service_agreement.no' => false,
                ],
                'data' => null,
                'attr' => ['class' => 'form-element'],
            ])
            ->add('hostingProvider', ChoiceType::class, [
                'required' => false,
                'label' => 'service_agreement.hosting_provider',
                'label_attr' => ['class' => 'label'],
                'choices' => HostingProviderEnum::cases(),
                'choice_label' => fn (HostingProviderEnum $enum) => $enum->value,
                'attr' => ['class' => 'form-element'],
            ])
            ->add('active', ChoiceType::class, [
                'required' => false,
                'label' => 'service_agreement.is_active',
                'label_attr' => ['class' => 'label'],
                'choices' => [
                    'service_agreement.yes' => true,
                    'service_agreement.no' => false,
                ],
                'attr' => ['class' => 'form-element'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'data_class' => ServiceAgreementFilterData::class,
            'attr' => ['class' => 'service_agreement_filter'],
        ]);
    }
}
