<?php

namespace App\Model\Reports;

/*
 * Kind is a term on a worklog in Leantime:
 * https://github.com/Leantime/leantime/blob/80c4542e19692e423820bd9030907070d281571e/app/Domain/Timesheets/Services/Timesheets.php#L22
 * */
enum WorkloadReportBillableKindsEnum: string
{
    public const GENERAL_BILLABLE = 'GENERAL_BILLABLE';
    public const PROJECTMANAGEMENT = 'PROJECTMANAGEMENT';
    public const DEVELOPMENT = 'DEVELOPMENT';
    public const TESTING = 'TESTING';

    public static function getValues(): array
    {
        $reflectionClass = new \ReflectionClass(__CLASS__);

        return $reflectionClass->getConstants();
    }
}
