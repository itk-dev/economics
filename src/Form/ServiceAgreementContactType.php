<?php

namespace App\Form;

use App\Entity\ContactRole;
use App\Entity\ServiceAgreementContact;
use App\Repository\ContactRoleRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Service\ResetInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<ServiceAgreementContact>
 */
class ServiceAgreementContactType extends AbstractType implements ResetInterface
{
    /**
     * The vocabulary as it stood when this request started.
     *
     * Cached because the options are built once per contact row and again for
     * every PRE_SUBMIT rebuild, and the stored vocabulary cannot change within
     * a request — a role introduced by the submit arrives via $extraNames.
     *
     * @var string[]|null
     */
    private ?array $existingNames = null;

    /**
     * The same vocabulary keyed by its own lowercased names.
     *
     * @var array<string, string>|null
     */
    private ?array $existingByLower = null;

    public function __construct(
        private readonly ContactRoleRepository $contactRoleRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Request scoped, on a shared service — see ContactRoleCollectionTransformer.
     * Without this, a role created in one request would be missing from the
     * suggestions of the next wherever the container is reused.
     */
    public function reset(): void
    {
        $this->existingNames = null;
        $this->existingByLower = null;
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

            if (!\is_array($data)) {
                return;
            }

            $submitted = $data['roles'] ?? null;

            if (!\is_array($submitted) || [] === $submitted) {
                return;
            }

            $names = array_map(
                fn (mixed $name) => $this->canonicalName(\is_scalar($name) ? (string) $name : ''),
                $submitted
            );
            $names = array_values(array_filter($names, fn (string $name) => '' !== $name));

            // The submitted data is rewritten, not just added to: the choice
            // list below carries the canonical spelling, so leaving a typed
            // "fakturering" in the data would fail the choice constraint, and
            // carrying both spellings would offer one role twice.
            $data['roles'] = $names;
            $event->setData($data);

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
     * The name as it will be stored: an existing role's own spelling when one
     * matches case-insensitively, otherwise ContactRole's normalisation.
     *
     * Only the vocabulary as it stood when the request started is consulted. A
     * role introduced by an earlier sibling contact in the same collection
     * submit is not in it, so a later sibling spelling it differently keeps its
     * own casing in the choice list — the transformer still hands both contacts
     * the one entity, and the redirect re-renders the list clean.
     */
    private function canonicalName(string $name): string
    {
        $name = ContactRole::normalizeName($name);

        if ('' === $name) {
            return '';
        }

        $this->existingNames ??= $this->contactRoleRepository->findAllNames();
        $this->existingByLower ??= array_combine(
            array_map(fn (string $existing) => mb_strtolower($existing), $this->existingNames),
            $this->existingNames
        );

        return $this->existingByLower[mb_strtolower($name)] ?? $name;
    }

    /**
     * @param string[] $extraNames names submitted but not yet in the vocabulary
     *
     * @return array<string, mixed>
     */
    private function rolesOptions(array $extraNames = []): array
    {
        $this->existingNames ??= $this->contactRoleRepository->findAllNames();

        $names = array_merge($this->existingNames, $extraNames);
        $names = array_values(array_unique(array_filter($names, fn (string $name) => '' !== trim($name))));

        return [
            'choices' => array_combine($names, $names),
            'label' => 'service_agreement.contact_roles',
            'help' => 'service_agreement.contact_roles_help',
            'label_attr' => ['class' => 'label'],
            'attr' => [
                'class' => 'form-element',
                'data-tags-target' => 'select',
            ],
            'help_attr' => ['class' => 'form-help'],
            'row_attr' => [
                'class' => 'form-row',
                // On the row, not the select: Choices.js wraps the select and so
                // moves it, and a controller mounted on a moving element gets
                // disconnected and reconnected forever. The row never moves, and
                // being inside the entry it still initialises a row added from
                // the prototype.
                'data-controller' => 'tags',
                // Translated here rather than passed as a key: they reach the
                // widget through Choices.js, which never sees the translator.
                'data-tags-placeholder-value' => $this->translator->trans('service_agreement.contact_roles_placeholder'),
                // %name% is left standing on purpose — trans() without
                // parameters returns it verbatim and the controller fills in
                // whatever the user has typed so far.
                'data-tags-add-label-value' => $this->translator->trans('service_agreement.contact_roles_add_new'),
                // Choices.js otherwise renders its own English defaults here.
                'data-tags-no-results-value' => $this->translator->trans('service_agreement.contact_roles_no_results'),
                'data-tags-no-choices-value' => $this->translator->trans('service_agreement.contact_roles_no_choices'),
            ],
        ];
    }
}
