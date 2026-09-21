<?php

namespace App\Tests\Unit\Enum;

use App\Enum\BillableKindsEnum;
use App\Enum\IssueStatusEnum;
use App\Enum\NonBillableEpicsEnum;
use App\Enum\NonBillableVersionsEnum;
use App\Enum\SubscriptionFrequencyEnum;
use App\Enum\SubscriptionSubjectEnum;
use PHPUnit\Framework\TestCase;

/**
 * The enums used as form choices all expose `getAsArray()`, which maps each
 * case name to its backing value.
 */
class EnumChoiceListTest extends TestCase
{
    /**
     * @dataProvider choiceEnumProvider
     *
     * @param class-string $enum
     */
    public function testGetAsArrayMapsEveryCaseNameToItsValue(string $enum): void
    {
        $expected = [];
        foreach ($enum::cases() as $case) {
            $expected[$case->name] = $case->value;
        }

        $this->assertSame($expected, $enum::getAsArray());
    }

    /**
     * @dataProvider choiceEnumProvider
     *
     * @param class-string $enum
     */
    public function testGetAsArrayIsNotEmpty(string $enum): void
    {
        $this->assertNotEmpty($enum::getAsArray());
    }

    /**
     * @return array<string, array{class-string}>
     */
    public static function choiceEnumProvider(): array
    {
        return [
            'billable kinds' => [BillableKindsEnum::class],
            'issue status' => [IssueStatusEnum::class],
            'non billable epics' => [NonBillableEpicsEnum::class],
            'non billable versions' => [NonBillableVersionsEnum::class],
            'subscription frequency' => [SubscriptionFrequencyEnum::class],
            'subscription subject' => [SubscriptionSubjectEnum::class],
        ];
    }

    public function testIssueStatusExposesEveryTrackerState(): void
    {
        $this->assertSame(
            [
                'READY_FOR_PLANNING' => 'ready for planning',
                'NEW' => 'new',
                'IN_PROGRESS' => 'in progress',
                'WAITING' => 'waiting',
                'BLOCKED' => 'blocked',
                'IN_REVIEW' => 'in review',
                'READY_FOR_RELEASE' => 'ready for release',
                'READY_FOR_TEST' => 'ready for test',
                'DONE' => 'done',
                'ARCHIVED' => 'archived',
                'OTHER' => 'other',
            ],
            IssueStatusEnum::getAsArray()
        );
    }

    public function testBillableKindsExposesTheLeantimeKinds(): void
    {
        $this->assertSame(
            [
                'GENERAL_BILLABLE' => 'GENERAL_BILLABLE',
                'PROJECTMANAGEMENT' => 'PROJECTMANAGEMENT',
                'DEVELOPMENT' => 'DEVELOPMENT',
                'TESTING' => 'TESTING',
            ],
            BillableKindsEnum::getAsArray()
        );
    }

    public function testSubscriptionFrequencyExposesMonthlyAndQuarterly(): void
    {
        $this->assertSame(
            ['FREQUENCY_MONTHLY' => 'monthly', 'FREQUENCY_QUARTERLY' => 'quarterly'],
            SubscriptionFrequencyEnum::getAsArray()
        );
    }
}
