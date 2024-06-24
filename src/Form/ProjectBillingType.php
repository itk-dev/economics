<?php

namespace App\Form;

use App\Entity\ProjectBilling;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProjectBillingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, [
                'required' => true,
                'label' => 'project_billing.field_name',
                'label_attr' => ['class' => 'label'],
                'row_attr' => ['class' => 'form-row'],
                'attr' => ['class' => 'form-element'],
                'help' => 'project_billing.field_name_helptext',
            ])
            ->add('project', null, [
                'required' => true,
                'label' => 'project_billing.field_project',
                'label_attr' => ['class' => 'label'],
                'row_attr' => ['class' => 'form-row'],
                'attr' => ['class' => 'form-element'],
                'help' => 'project_billing.field_project_helptext',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('p')
                        ->where('p.include IS NOT NULL')
                        ->orderBy('p.name', 'ASC');
                },
            ])
            ->add('periodStart', DateType::class, [
                'required' => true,
                'label' => 'project_billing.field_period_start',
                'label_attr' => ['class' => 'label'],
                'row_attr' => ['class' => 'form-row'],
                'help' => 'project_billing.field_period_start_helptext',
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['class' => 'form-element'],
            ])
            ->add('periodEnd', DateType::class, [
                'required' => true,
                'label' => 'project_billing.field_period_end',
                'label_attr' => ['class' => 'label'],
                'row_attr' => ['class' => 'form-row'],
                'attr' => ['class' => 'form-element'],
                'help' => 'project_billing.field_period_end_helptext',
                'widget' => 'single_text',
                'html5' => true,
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'project_billing.field_description',
                'label_attr' => ['class' => 'label'],
                'row_attr' => ['class' => 'form-row'],
                'attr' => ['class' => 'form-element'],
                'help' => 'project_billing.field_description_help',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProjectBilling::class,
        ]);
    }
}
