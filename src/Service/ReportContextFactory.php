<?php

namespace App\Service;

use App\Entity\User;
use App\Model\Reports\HasGroupFilter;
use App\Model\Reports\ReportContext;

class ReportContextFactory
{
    /**
     * Builds a ReportContext from form data and the current user.
     *
     * @param object $formData a report form-data DTO; if it implements HasGroupFilter
     *                         the selected group is carried into the context
     */
    public function fromForm(object $formData, ?User $user, int $year): ReportContext
    {
        $group = $formData instanceof HasGroupFilter ? $formData->getGroup() : null;
        $hidden = $user?->getHiddenWorkers() ?? [];

        return new ReportContext($year, $group, $hidden);
    }
}
