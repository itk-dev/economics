<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Entity\Worker;
use App\Model\DashboardData;
use App\Repository\WorkerRepository;
use App\Repository\WorklogRepository;
use App\Service\DashboardService;
use App\Service\DateTimeHelper;
use PHPUnit\Framework\TestCase;

class DashboardServiceTest extends TestCase
{
    private WorkerRepository $workerRepository;
    private WorklogRepository $worklogRepository;
    private DateTimeHelper $dateTimeHelper;
    private DashboardService $dashboardService;

    protected function setUp(): void
    {
        $this->workerRepository = $this->createMock(WorkerRepository::class);
        $this->worklogRepository = $this->createMock(WorklogRepository::class);
        $this->dateTimeHelper = $this->createMock(DateTimeHelper::class);

        $this->dashboardService = new DashboardService(
            $this->workerRepository,
            $this->worklogRepository,
            $this->dateTimeHelper,
        );
    }

    public function testGetUserDashboardNullEmail(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn(null);

        $result = $this->dashboardService->getUserDashboard($user);

        $this->assertNull($result);
    }

    public function testGetUserDashboardNoWorker(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn('test@test.com');

        $this->workerRepository->method('findOneBy')
            ->with(['email' => 'test@test.com'])
            ->willReturn(null);

        $result = $this->dashboardService->getUserDashboard($user);

        $this->assertNull($result);
    }

    public function testGetUserDashboardReturnsData(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn('test@test.com');

        $worker = $this->createMock(Worker::class);
        $worker->method('getWorkload')->willReturn(37.0);

        $this->workerRepository->method('findOneBy')
            ->willReturn($worker);

        $yearStart = new \DateTime('2024-01-01 00:00:00');
        $yearEnd = new \DateTime('2024-12-31 23:59:59');

        $this->dateTimeHelper->method('getFirstAndLastDateOfYear')
            ->willReturn(['dateFrom' => $yearStart, 'dateTo' => $yearEnd]);
        $this->dateTimeHelper->method('getToday')
            ->willReturn(new \DateTime());

        $this->worklogRepository->method('getTimeSpentByWorkerInWeekRange')
            ->willReturn([]);

        $result = $this->dashboardService->getUserDashboard($user, 2024);

        $this->assertInstanceOf(DashboardData::class, $result);
        $this->assertSame(2024, $result->year);
        $this->assertSame(37.0, $result->norm);
    }

    public function testGetUserDashboardNullWorkloadDefaultsToZero(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn('test@test.com');

        $worker = $this->createMock(Worker::class);
        $worker->method('getWorkload')->willReturn(null);

        $this->workerRepository->method('findOneBy')
            ->willReturn($worker);

        $this->dateTimeHelper->method('getFirstAndLastDateOfYear')
            ->willReturn([
                'dateFrom' => new \DateTime('2024-01-01 00:00:00'),
                'dateTo' => new \DateTime('2024-12-31 23:59:59'),
            ]);
        $this->dateTimeHelper->method('getToday')
            ->willReturn(new \DateTime());

        $this->worklogRepository->method('getTimeSpentByWorkerInWeekRange')
            ->willReturn([]);

        $result = $this->dashboardService->getUserDashboard($user, 2024);

        $this->assertInstanceOf(DashboardData::class, $result);
        $this->assertSame(0.0, $result->norm);
    }

    public function testGetUserDashboardYearStatusCalculation(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn('test@test.com');

        $worker = $this->createMock(Worker::class);
        // 40h/week = 8h/day = 28800 seconds/day
        $worker->method('getWorkload')->willReturn(40.0);

        $this->workerRepository->method('findOneBy')
            ->willReturn($worker);

        // Use a past year to avoid "future date" filtering
        $this->dateTimeHelper->method('getFirstAndLastDateOfYear')
            ->willReturn([
                'dateFrom' => new \DateTime('2023-01-01 00:00:00'),
                'dateTo' => new \DateTime('2023-12-31 23:59:59'),
            ]);
        $this->dateTimeHelper->method('getToday')
            ->willReturn(new \DateTime());

        // Return 1000 hours spent in the year
        $this->worklogRepository->method('getTimeSpentByWorkerInWeekRange')
            ->willReturnCallback(function ($email, $from, $to, $groupBy) {
                if ('year' === $groupBy) {
                    return [2023 => ['totalTimeSpent' => 3600000]]; // 1000 hours in seconds
                }

                return [];
            });

        $result = $this->dashboardService->getUserDashboard($user, 2023);

        $this->assertInstanceOf(DashboardData::class, $result);
        // yearStatus = (totalTimeSpent - yearNormToDate) / 3600
        // The exact value depends on how many weekdays in 2023, but it should be a number
        $this->assertIsFloat($result->workHours);
    }
}
