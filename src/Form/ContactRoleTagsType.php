<?php

namespace App\Form;

use App\Form\DataTransformer\ContactRoleCollectionTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The role tags on a contact.
 *
 * A dedicated type rather than a plain ChoiceType, because the field is rebuilt
 * on submit to widen its choice list, and only a type can re-attach the
 * transformer that turns names back into ContactRole entities.
 *
 * @extends AbstractType<mixed>
 */
class ContactRoleTagsType extends AbstractType
{
    public function __construct(
        private readonly ContactRoleCollectionTransformer $contactRoleCollectionTransformer,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer($this->contactRoleCollectionTransformer);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'multiple' => true,
            'expanded' => false,
            'required' => false,
            // Role names are user-supplied data, not translation keys.
            'choice_translation_domain' => false,
        ]);
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }
}
