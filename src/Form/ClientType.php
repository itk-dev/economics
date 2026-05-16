<?php

namespace App\Form;

use App\Entity\Client;
use App\Enum\ClientTypeEnum;
use App\Repository\ClientRepository;
use App\Repository\VersionRepository;
use App\Service\ProjectBillingService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

class ClientType extends AbstractType
{
    public function __construct(private readonly VersionRepository $versionRepository, private readonly ClientRepository $clientRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'create_client_form.client_name.label',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'help' => 'create_client_form.client_name.help',
                'required' => true,
                'row_attr' => ['class' => 'form-row'],
            ])
            ->add('contact', TextType::class, [
                'label' => 'create_client_form.client_contact.label',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'help' => 'create_client_form.client_contact.help',
                'required' => true,
                'row_attr' => ['class' => 'form-row'],
            ])
            ->add('standardPrice', NumberType::class, [
                'label' => 'create_client_form.client_standardPrice.label',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element', 'min' => 0],
                'help_attr' => ['class' => 'form-help'],
                'help' => new TranslatableMessage('create_client_form.client_standardPrice.help', ['%standard_price%' => $options['standard_price']]),
                'required' => false,
                'row_attr' => ['class' => 'form-row'],
                'html5' => true,
            ])
            ->add('type', EnumType::class, [
                'class' => ClientTypeEnum::class,
                'label' => 'create_client_form.type.label',
                'label_attr' => ['class' => 'label'],
                'choice_label' => fn ($choice) => match ($choice) {
                    ClientTypeEnum::INTERNAL => 'client_type_enum.internal',
                    ClientTypeEnum::EXTERNAL => 'client_type_enum.external',
                    default => null,
                },
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'help' => 'create_client_form.type.help',
                'required' => true,
                'row_attr' => ['class' => 'form-row'],
            ])
            ->add('customerKey', TextType::class, [
                'label' => 'create_client_form.customer_key.label',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'help' => 'create_client_form.customer_key.help',
                'row_attr' => ['class' => 'form-row'],
            ])
            ->add('psp', TextType::class, [
                'label' => 'create_client_form.client_psp.label',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'help' => 'create_client_form.client_psp.help',
                'row_attr' => ['class' => 'form-row'],
                'required' => false,
            ])
            ->add('ean', TextType::class, [
                'label' => 'create_client_form.client_ean.label',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'help' => 'create_client_form.client_ean.help',
                'row_attr' => ['class' => 'form-row'],
                'required' => false,
            ])
            ->add('versionName', ChoiceType::class, [
                'label' => 'create_client_form.version_id.label',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element ', 'data-choices-target' => 'choices'],
                'help_attr' => ['class' => 'form-help'],
                'help' => 'create_client_form.version_id.help',
                'row_attr' => ['class' => 'form-row form-choices'],
                'required' => false,
                'choices' => $this->getVersionOptions($builder->getData()),
            ]);
    }

    private function getVersionOptions(?Client $client): array
    {
        $versions = $this->versionRepository->findAll();

        $result = [];

        // Make sure that the current client's version name is always included
        $name = $client?->getVersionName();
        if (null !== $name) {
            $result[$name] = $name;
        }

        foreach ($versions as $version) {
            $name = $version->getName();

            // Only include versions that start with project billing prefix.
            if (null !== $name && str_starts_with($name, ProjectBillingService::PROJECT_BILLING_VERSION_PREFIX)) {
                // A version name should only be assigned to one client to ensure a unique project billing mapping.
                $alreadyAssigned = $this->clientRepository->findBy(['versionName' => $version->getName()]);
                if (count($alreadyAssigned) > 0) {
                    continue;
                }

                $result[$name] = $name;
            }
        }

        return $result;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'data_class' => Client::class,
            ])
            ->setRequired('standard_price')
            ->setAllowedTypes('standard_price', 'float');
    }
}
