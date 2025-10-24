<?php

namespace App\Form;

use App\Entity\Client;
use App\Entity\Project;
use App\Entity\ServiceAgreement;
use App\Entity\User;
use App\Entity\Worker;
use App\Enum\HostingProviderEnum;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ServiceAgreementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('projectId', EntityType::class, [
                'class' => Project::class,
                'label' => 'serviceAgreement.project',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row']
            ])
            ->add('clientId', EntityType::class, [
                'class' => Client::class,
                'label' => 'serviceAgreement.client',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row']
            ])
            ->add('hostingProvider', ChoiceType::class, [
                'choices' => HostingProviderEnum::cases(),
                'choice_label' => fn($choice) => $choice->value,
                'label' => 'serviceAgreement.hosting_provider',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row']
            ])
            ->add('documentUrl', null, [
                'label' => 'serviceAgreement.document_url',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row']
            ])
            ->add('price', null, [
                'label' => 'serviceAgreement.price',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row']
            ])
            ->add('projectLeadId', EntityType::class, [
                'class' => Worker::class,
                'label' => 'serviceAgreement.project_lead_id',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row']
            ])
            ->add('validFrom', null, [
                'widget' => 'single_text',
                'label' => 'serviceAgreement.valid_from',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row']
            ])
            ->add('validTo', null, [
                'widget' => 'single_text',
                'label' => 'serviceAgreement.valid_to',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row']
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'serviceAgreement.is_active',
                'label_attr' => ['class' => 'label'],
                'help_attr' => ['class' => 'form-help'],
            ])
            ->add('hasCybersecurityAgreement', CheckboxType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'serviceAgreement.has_cybersecurity_agreement',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'toggle-additional-info'],
                'help_attr' => ['class' => 'form-help'],
            ])
            ->add('cybersecurityAgreementData', CybersecurityAgreementType::class, [
                'mapped' => false,
                'required' => false,
                'label' => false,
                'attr' => ['class' => 'd-block'],
                'data_class' => CybersecurityAgreementType::class,

            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ServiceAgreement::class,
        ]);
    }
}
