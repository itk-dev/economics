<?php

namespace App\Model\Reports;

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
