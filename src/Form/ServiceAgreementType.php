<?php

namespace App\Form;

use App\Entity\Client;
use App\Entity\Project;
use App\Entity\ServiceAgreement;
use App\Entity\Worker;
use App\Enum\HostingProviderEnum;
use App\Enum\SystemOwnerNoticeEnum;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Url;

class ServiceAgreementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('project', EntityType::class, [
                'class' => Project::class,
                'label' => 'service_agreement.project',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row'],
            ])
            ->add('client', EntityType::class, [
                'class' => Client::class,
                'label' => 'service_agreement.client',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row'],
            ])
            ->add('hostingProvider', ChoiceType::class, [
                'choices' => HostingProviderEnum::cases(),
                'choice_label' => fn ($choice) => $choice->value,
                'label' => 'service_agreement.hosting_provider',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row'],
            ])
            ->add('price', NumberType::class, [
                'label' => 'service_agreement.price',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row service-agreement-price'],
                'html5' => true,
            ])
            ->add('SystemOwnerNotice', EnumType::class, [
                'class' => SystemOwnerNoticeEnum::class,
                'label' => 'service_agreement.system_owner_notice',
                'label_attr' => ['class' => 'label'],
                'choice_label' => fn ($choice) => match ($choice) {
                    SystemOwnerNoticeEnum::ON_UPDATE => 'system_owner_notice_enum.on_update',
                    SystemOwnerNoticeEnum::ON_SERVER => 'system_owner_notice_enum.on_server',
                    SystemOwnerNoticeEnum::NEVER => 'system_owner_notice_enum.never',
                    default => null,
                },
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row service-agreement-price'],
            ])
            ->add('documentUrl', UrlType::class, [
                'label' => 'service_agreement.document_url',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'constraints' => new Url(),
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row'],
                'required' => false,
            ])
            ->add('projectLead', EntityType::class, [
                'class' => Worker::class,
                'label' => 'service_agreement.project_lead_id',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row'],
            ])
            ->add('validFrom', DateType::class, [
                'widget' => 'single_text',
                'label' => 'service_agreement.valid_from',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row'],
            ])
            ->add('validTo', DateType::class, [
                'widget' => 'single_text',
                'label' => 'service_agreement.valid_to',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row'],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'service_agreement.is_active',
                'label_attr' => ['class' => 'label toggle-label'],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row select-none'],
                'attr' => ['style' => 'margin-left: 10px;'],
                'required' => false,
                'data' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ServiceAgreement::class,
            'cascade_validation' => true,
        ]);
    }
}
