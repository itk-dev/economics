<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CombinedServiceAgreementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('serviceAgreement', ServiceAgreementType::class, [
                'label' => false
            ])
            ->add('hasCybersecurityAgreement', CheckboxType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'service_agreement.has_cybersecurity_agreement',
                'label_attr' => ['class' => 'label toggle-label'],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row select-none'],
                'attr' => ['class' => 'ml-1'],
                'data' => $options['data']['hasCybersecurityAgreement'] ?? false
            ])
            ->add('cybersecurityAgreement', CybersecurityAgreementType::class, [
                'label' => false
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'hasCybersecurityAgreement' => false,
            'data_class' => null
        ]);

    }
}
