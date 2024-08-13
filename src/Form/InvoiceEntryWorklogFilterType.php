<?php

namespace App\Form;

use App\Model\Invoices\InvoiceEntryWorklogsFilterData;
use App\Repository\InvoiceRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InvoiceEntryWorklogFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('isBilled', ChoiceType::class, [
                'required' => false,
                'label' => 'worklog.is_billed',
                'label_attr' => ['class' => 'label'],
                'choices' => [
                    'worklog.is_billed_false' => false,
                    'worklog.is_billed_true' => true,
                ],
                'help' => 'worklog.is_billed_helptext',
                'attr' => ['class' => 'form-element'],
            ])
            ->add('periodFrom', DateType::class, [
                'required' => false,
                'label' => 'worklog.period_from',
                'label_attr' => ['class' => 'label'],
                'row_attr' => ['class' => 'form-row'],
                'help' => 'worklog.period_from_helptext',
                'data' => $options['periodFrom'],
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['class' => 'form-element'],
            ])
            ->add('periodTo', DateType::class, [
                'required' => false,
                'label' => 'worklog.period_to',
                'label_attr' => ['class' => 'label'],
                'row_attr' => ['class' => 'form-row'],
                'attr' => ['class' => 'form-element'],
                'help' => 'worklog.period_to_helptext',
                'data' => $options['periodTo'],
                'widget' => 'single_text',
                'html5' => true,
            ])
            ->add('worker', null, [
                'required' => false,
                'label' => 'worklog.worker',
                'label_attr' => ['class' => 'label'],
                'row_attr' => ['class' => 'form-row'],
                'attr' => ['class' => 'form-element'],
                'help' => 'worklog.worker_helptext',
            ])
            ->add('onlyAvailable', ChoiceType::class, [
                'required' => true,
                'label' => 'worklog.only_available',
                'label_attr' => ['class' => 'label'],
                'choices' => [
                    'worklog.only_available_true' => true,
                    'worklog.only_available_false' => false,
                ],
                'help' => 'worklog.only_available_helptext',
                'attr' => ['class' => 'form-element'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'data_class' => InvoiceEntryWorklogsFilterData::class,
            'periodFrom' => null,
            'periodTo' => null,
        ]);
    }
}
