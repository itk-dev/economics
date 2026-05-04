<?php

namespace App\Form\Extension;

use App\Entity\WorkerGroup;
use App\Model\Reports\HasGroupFilter;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Adds the WorkerGroup filter field to any form whose data class implements
 * App\Model\Reports\HasGroupFilter. Mirrors the field definition used by
 * App\Form\PlanningType so the UX is identical across reports and planning.
 */
class ReportFilterExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $dataClass = $options['data_class'] ?? null;

        if (!is_string($dataClass) || !is_a($dataClass, HasGroupFilter::class, true)) {
            return;
        }

        $builder->add('group', EntityType::class, [
            'class' => WorkerGroup::class,
            'label' => 'reports.group',
            'label_attr' => ['class' => 'label'],
            'attr' => ['class' => 'form-element ', 'onchange' => 'this.form.submit()'],
            'help_attr' => ['class' => 'form-help'],
            'row_attr' => ['class' => 'form-row'],
            'required' => false,
            'placeholder' => 'reports.select_group',
        ]);
    }
}
