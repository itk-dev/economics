<?php

namespace App\Tests\Unit\Service;

use App\Entity\Project;
use App\Entity\Subscription;
use App\Entity\Version;
use App\Enum\SubscriptionFrequencyEnum;
use App\Enum\SubscriptionSubjectEnum;
use App\Model\Reports\HourReportData;
use App\Repository\ProjectRepository;
use App\Repository\VersionRepository;
use App\Service\HourReportService;
use App\Service\SubscriptionHandlerService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class SubscriptionHandlerServiceTest extends TestCase
{
    private ProjectRepository $projectRepository;
    private VersionRepository $versionRepository;
    private HourReportService $hourReportService;
    private Environment $twig;
    private LoggerInterface $logger;
    private MailerInterface $mailer;
    private TranslatorInterface $translator;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->projectRepository = $this->createMock(ProjectRepository::class);
        $this->versionRepository = $this->createMock(VersionRepository::class);
        $this->hourReportService = $this->createMock(HourReportService::class);
        $this->twig = $this->createMock(Environment::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
    }

    private function createService(string $emailFromAddress = 'test@example.com'): SubscriptionHandlerService
    {
        return new SubscriptionHandlerService(
            $emailFromAddress,
            $this->projectRepository,
            $this->versionRepository,
            $this->hourReportService,
            $this->twig,
            $this->logger,
            $this->mailer,
            $this->translator,
            $this->entityManager,
        );
    }

    private function createValidSubscription(): Subscription
    {
        $subscription = new Subscription();
        $subscription->setSubject(SubscriptionSubjectEnum::HOUR_REPORT);
        $subscription->setFrequency(SubscriptionFrequencyEnum::FREQUENCY_MONTHLY);
        $subscription->setEmail('recipient@example.com');
        $subscription->setUrlParams([
            'hour_report' => [
                'project' => 1,
                'version' => null,
            ],
        ]);

        return $subscription;
    }

    private function createProject(): Project
    {
        $project = new Project();
        $project->setName('Test Project');

        return $project;
    }

    public function testHandleSubscriptionHourReportSuccess(): void
    {
        $service = $this->createService();
        $subscription = $this->createValidSubscription();
        $project = $this->createProject();
        $fromDate = new \DateTime('2026-01-01');
        $toDate = new \DateTime('2026-01-31');
        $reportData = new HourReportData(100.0, 200.0);

        $this->projectRepository->method('find')->with(1)->willReturn($project);
        $this->hourReportService->method('getHourReport')->willReturn($reportData);
        $this->twig->method('render')->willReturn('<html><body>Report</body></html>');
        $this->translator->method('trans')->willReturnCallback(fn (string $key) => $key);

        $this->mailer->expects($this->once())->method('send');
        $this->entityManager->expects($this->once())->method('persist')->with($subscription);
        $this->entityManager->expects($this->once())->method('flush');

        $service->handleSubscription($subscription, $fromDate, $toDate);

        $this->assertNotNull($subscription->getLastSent());
    }

    public function testHandleSubscriptionWithVersion(): void
    {
        $service = $this->createService();
        $subscription = $this->createValidSubscription();
        $subscription->setUrlParams([
            'hour_report' => [
                'project' => 1,
                'version' => 42,
            ],
        ]);

        $project = $this->createProject();
        $version = new Version();

        $this->projectRepository->method('find')->with(1)->willReturn($project);
        $this->versionRepository->method('findOneBy')->with(['versionId' => 42])->willReturn($version);
        $this->hourReportService->expects($this->once())
            ->method('getHourReport')
            ->with($project, $this->anything(), $this->anything(), $version)
            ->willReturn(new HourReportData(0, 0));
        $this->twig->method('render')->willReturn('<html>Report</html>');
        $this->translator->method('trans')->willReturnCallback(fn (string $key) => $key);
        $this->mailer->expects($this->once())->method('send');

        $service->handleSubscription($subscription, new \DateTime(), new \DateTime());
    }

    public function testHandleSubscriptionMissingUrlParamsThrowsException(): void
    {
        $service = $this->createService();
        $subscription = $this->createValidSubscription();
        $subscription->setUrlParams(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid subscription parameters.');

        $service->handleSubscription($subscription, new \DateTime(), new \DateTime());
    }

    public function testHandleSubscriptionProjectNotFoundThrowsException(): void
    {
        $service = $this->createService();
        $subscription = $this->createValidSubscription();

        $this->projectRepository->method('find')->willReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Project not found.');

        $service->handleSubscription($subscription, new \DateTime(), new \DateTime());
    }

    public function testHandleSubscriptionMissingEmailFromAddressThrowsException(): void
    {
        $service = $this->createService('');
        $subscription = $this->createValidSubscription();
        $project = $this->createProject();

        $this->projectRepository->method('find')->willReturn($project);
        $this->hourReportService->method('getHourReport')->willReturn(new HourReportData(0, 0));
        $this->twig->method('render')->willReturn('<html>Report</html>');
        $this->translator->method('trans')->willReturnCallback(fn (string $key) => $key);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Sender email address was not found in .env');

        $service->handleSubscription($subscription, new \DateTime(), new \DateTime());
    }

    public function testHandleSubscriptionMissingSubjectThrowsException(): void
    {
        $service = $this->createService();

        $subscription = new Subscription();
        $subscription->setFrequency(SubscriptionFrequencyEnum::FREQUENCY_MONTHLY);
        $subscription->setEmail('test@example.com');
        // Subject not set — will be null

        $subscription->setUrlParams([
            'hour_report' => ['project' => 1],
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Report type is not yet supported');

        $service->handleSubscription($subscription, new \DateTime(), new \DateTime());
    }

    public function testHandleSubscriptionMissingFrequencyThrowsException(): void
    {
        $service = $this->createService();

        $subscription = new Subscription();
        $subscription->setSubject(SubscriptionSubjectEnum::HOUR_REPORT);
        // Frequency not set — will be null
        $subscription->setEmail('test@example.com');
        $subscription->setUrlParams([
            'hour_report' => ['project' => 1],
        ]);

        $project = $this->createProject();
        $this->projectRepository->method('find')->willReturn($project);
        $this->hourReportService->method('getHourReport')->willReturn(new HourReportData(0, 0));
        $this->twig->method('render')->willReturn('<html>Report</html>');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Subject or frequency is missing from the subscription.');

        $service->handleSubscription($subscription, new \DateTime(), new \DateTime());
    }

    public function testHandleSubscriptionEmptyTranslationThrowsException(): void
    {
        $service = $this->createService();
        $subscription = $this->createValidSubscription();
        $project = $this->createProject();

        $this->projectRepository->method('find')->willReturn($project);
        $this->hourReportService->method('getHourReport')->willReturn(new HourReportData(0, 0));
        $this->twig->method('render')->willReturn('<html>Report</html>');
        $this->translator->method('trans')->willReturn('');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Translation for subject or frequency is missing.');

        $service->handleSubscription($subscription, new \DateTime(), new \DateTime());
    }
}
