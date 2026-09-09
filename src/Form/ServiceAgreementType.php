<?php

namespace App\Form;

use App\Entity\Client;
use App\Entity\Project;
use App\Entity\ServiceAgreement;
use App\Entity\ServiceAgreementContact;
use App\Entity\Worker;
use App\Enum\HostingProviderEnum;
use App\Enum\ServerSizeEnum;
use App\Enum\SystemOwnerNoticeEnum;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
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
            ->add('isActive', CheckboxType::class, [
                'label' => 'service_agreement.is_active',
                'label_attr' => ['class' => 'label toggle-label'],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row select-none'],
                'required' => false,
                'data' => true,
            ])
            ->add('isEol', CheckboxType::class, [
                'label' => 'service_agreement.is_eol',
                'label_attr' => ['class' => 'label toggle-label'],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row select-none'],
                'attr' => [
                    'data-eol-required-target' => 'checkbox',
                    'data-action' => 'eol-required#toggle',
                ],
                'required' => false,
            ])
            ->add('client', EntityType::class, [
                'class' => Client::class,
                'label' => 'service_agreement.client',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row'],
            ])
            ->add('contacts', CollectionType::class, [
                'entry_type' => ServiceAgreementContactType::class,
                // The class groups one contact's fields into a visible card;
                // without it the rows run together.
                'entry_options' => [
                    'label' => false,
                    'attr' => ['class' => 'service-agreement-contact'],
                ],
                'allow_add' => true,
                'allow_delete' => true,
                // Routes adds and removes through addContact()/removeContact(),
                // which is what keeps the owning side set.
                'by_reference' => false,
                'prototype' => true,
                // A row that was added and then left untouched is dropped rather
                // than saved as a nameless contact.
                'delete_empty' => fn (?ServiceAgreementContact $contact) => null === $contact
                    || (null === $contact->getName() && null === $contact->getEmail() && 0 === $contact->getRoles()->count()),
                'label' => 'service_agreement.contacts',
                'help' => 'service_agreement.contacts_help',
                'label_attr' => ['class' => 'label'],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row'],
                'required' => false,
            ])
            ->add('systemOwnerNotices', ChoiceType::class, [
                'choices' => SystemOwnerNoticeEnum::cases(),
                'choice_label' => fn (SystemOwnerNoticeEnum $choice) => match ($choice) {
                    SystemOwnerNoticeEnum::SERVERFLYTNING => 'system_owner_notice_enum.serverflytning',
                    SystemOwnerNoticeEnum::SIKKERHEDSPATCH => 'system_owner_notice_enum.sikkerhedspatch',
                    SystemOwnerNoticeEnum::CYBERSIKKERSHEDSOPDATERING => 'system_owner_notice_enum.cybersikkershedsopdatering',
                },
                'choice_value' => fn (?SystemOwnerNoticeEnum $choice) => $choice?->value,
                'multiple' => true,
                'expanded' => true,
                'label' => 'service_agreement.system_owner_notices',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'checkbox-inline-group'],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row'],
                'required' => false,
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
                'attr' => [
                    'class' => 'form-element',
                    'data-eol-required-target' => 'dateField',
                ],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row'],
                'required' => false,
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
            ->add('dedicatedServer', CheckboxType::class, [
                'label' => 'service_agreement.dedicated_server',
                'label_attr' => ['class' => 'label toggle-label'],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row select-none'],
                'required' => false,
            ])
            ->add('serverSize', ChoiceType::class, [
                'choices' => ServerSizeEnum::cases(),
                'choice_label' => fn (ServerSizeEnum $choice) => 'server_size_enum.'.$choice->value,
                'choice_value' => fn (?ServerSizeEnum $choice) => $choice?->value,
                'label' => 'service_agreement.server_size',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row'],
                'required' => false,
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
            ->add('price', NumberType::class, [
                'label' => 'service_agreement.operations_price',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row service-agreement-price'],
                'html5' => true,
            ])
            ->add('projectLead', EntityType::class, [
                'class' => Worker::class,
                'label' => 'service_agreement.project_lead_id',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row'],
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
