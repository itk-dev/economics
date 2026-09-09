<?php

namespace App\Form;

use App\Entity\ServiceAgreementContact;
use App\Repository\ContactRoleRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<ServiceAgreementContact>
 */
class ServiceAgreementContactType extends AbstractType
{
    public function __construct(
        private readonly ContactRoleRepository $contactRoleRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'service_agreement.contact_name',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row'],
                'required' => false,
            ])
            ->add('email', EmailType::class, [
                'label' => 'service_agreement.contact_email',
                'label_attr' => ['class' => 'label'],
                'attr' => ['class' => 'form-element'],
                'help_attr' => ['class' => 'form-help'],
                'row_attr' => ['class' => 'form-row'],
                'required' => false,
            ])
            ->add('roles', ContactRoleTagsType::class, $this->rolesOptions());

        // A role typed into the widget is submitted as a value the rendered
        // choice list never held, so the field is rebuilt around it before the
        // choice constraint runs.
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            $submitted = \is_array($data) ? ($data['roles'] ?? null) : null;

            if (!\is_array($submitted) || [] === $submitted) {
                return;
            }

            $names = array_map(fn (mixed $name) => \is_scalar($name) ? (string) $name : '', $submitted);

            $event->getForm()->add('roles', ContactRoleTagsType::class, $this->rolesOptions($names));
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ServiceAgreementContact::class,
        ]);
    }

    /**
     * @param string[] $extraNames names submitted but not yet in the vocabulary
     *
     * @return array<string, mixed>
     */
    private function rolesOptions(array $extraNames = []): array
    {
        $names = array_merge($this->contactRoleRepository->findAllNames(), $extraNames);
        $names = array_values(array_unique(array_filter($names, fn (string $name) => '' !== trim($name))));

        return [
            'choices' => array_combine($names, $names),
            'label' => 'service_agreement.contact_roles',
            'help' => 'service_agreement.contact_roles_help',
            'label_attr' => ['class' => 'label'],
            'attr' => [
                'class' => 'form-element',
                // Its own controller instance, so a row added from the
                // prototype initialises itself.
                'data-controller' => 'tags',
            ],
            'help_attr' => ['class' => 'form-help'],
            'row_attr' => ['class' => 'form-row'],
        ];
    }
}
