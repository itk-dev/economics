<?php

namespace App\Tests\Integration\Controller;

use App\Entity\Project;
use App\Entity\Subscription;
use App\Entity\User;
use App\Entity\Version;
use App\Enum\SubscriptionFrequencyEnum;
use App\Enum\SubscriptionSubjectEnum;
use Symfony\Component\DomCrawler\Crawler;

class SubscriptionFlowTest extends AbstractTransactionalFlowTestCase
{
    private const EMAIL = 'report@test.local';

    private int $userId;
    private int $projectId;
    private string $projectName;
    private int $versionId;
    private string $versionName;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootTransactionalClient('ROLE_REPORT');

        $this->userId = $this->requireId($this->findOne(User::class, ['email' => self::EMAIL])->getId());

        $version = $this->findOne(Version::class);
        $project = $this->requireEntity(Project::class, $version->getProject());
        $this->versionId = $this->requireId($version->getId());
        $this->versionName = $this->requireString($version->getName());
        $this->projectId = $this->requireId($project->getId());
        $this->projectName = $this->requireString($project->getName());
    }

    public function testIndexIsEmptyWithoutSubscriptions(): void
    {
        $crawler = $this->client->request('GET', '/admin/subscription/');

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('td.subscription-details'));
    }

    public function testIndexListsTheUsersSubscriptions(): void
    {
        $this->persistSubscription(SubscriptionFrequencyEnum::FREQUENCY_MONTHLY);

        $crawler = $this->client->request('GET', '/admin/subscription/');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString($this->projectName, $crawler->html());
    }

    public function testIndexHidesOtherUsersSubscriptions(): void
    {
        $this->persistSubscription(SubscriptionFrequencyEnum::FREQUENCY_MONTHLY, 'someone-else@test.local');

        $crawler = $this->client->request('GET', '/admin/subscription/');

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('td.subscription-details'));
    }

    public function testIndexFilterKeepsMatchingSubscriptions(): void
    {
        $this->persistSubscription(SubscriptionFrequencyEnum::FREQUENCY_MONTHLY);

        $crawler = $this->submitFilter($this->projectName);

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString($this->projectName, $crawler->html());
    }

    public function testIndexFilterExcludesNonMatchingSubscriptions(): void
    {
        $this->persistSubscription(SubscriptionFrequencyEnum::FREQUENCY_MONTHLY);

        $crawler = $this->submitFilter('nothing-matches-this-filter');

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('td.subscription-details'));
    }

    public function testDeleteRemovesTheSubscription(): void
    {
        $id = $this->persistSubscription(SubscriptionFrequencyEnum::FREQUENCY_MONTHLY);

        $this->client->request('GET', sprintf('/admin/subscription/%d/delete', $id));

        $this->assertResponseRedirects('/admin/subscription/');

        $this->entityManager->clear();
        $this->assertNull($this->findByIdOrNull(Subscription::class, $id));
    }

    public function testCheckWithoutAProjectIsNotFound(): void
    {
        $this->postCheck(['hour_report' => ['version' => (string) $this->versionId]]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testCheckRejectsUnsupportedReportTypes(): void
    {
        $this->postCheck(['unsupported_report' => ['project' => (string) $this->projectId]]);

        $this->assertResponseStatusCodeSame(400);
        $this->assertSame(
            ['error' => 'Unsupported report type'],
            $this->responseJson()
        );
    }

    public function testCheckReturnsNoFrequenciesWhenNotSubscribed(): void
    {
        $this->postCheck(['hour_report' => ['project' => (string) $this->projectId]]);

        $this->assertResponseIsSuccessful();
        $this->assertSame([], $this->responseJson());
    }

    public function testCheckSubscribesAndThenUnsubscribes(): void
    {
        $payload = [
            'hour_report' => [
                'project' => (string) $this->projectId,
                'subscriptionType' => SubscriptionFrequencyEnum::FREQUENCY_MONTHLY->value,
            ],
        ];

        $this->postCheck($payload);
        $this->assertResponseIsSuccessful();
        $subscribed = $this->responseJson();
        $this->assertSame('subscribed', $subscribed['action']);
        $this->assertSame('monthly', $subscribed['frequencies']);

        $this->postCheck($payload);
        $this->assertResponseIsSuccessful();
        $unsubscribed = $this->responseJson();
        $this->assertSame('unsubscribed', $unsubscribed['action']);
        $this->assertSame('', $unsubscribed['frequencies']);
    }

    public function testCheckReportsFrequenciesInEnumOrder(): void
    {
        foreach ([SubscriptionFrequencyEnum::FREQUENCY_QUARTERLY, SubscriptionFrequencyEnum::FREQUENCY_MONTHLY] as $frequency) {
            $this->postCheck([
                'hour_report' => [
                    'project' => (string) $this->projectId,
                    'subscriptionType' => $frequency->value,
                ],
            ]);
            $this->assertResponseIsSuccessful();
        }

        $this->postCheck(['hour_report' => ['project' => (string) $this->projectId]]);

        $this->assertResponseIsSuccessful();
        $this->assertSame(
            'monthly, quarterly',
            $this->responseJson()['frequencies']
        );
    }

    public function testCheckDropsTheDateRangeBeforeMatching(): void
    {
        $this->postCheck([
            'hour_report' => [
                'project' => (string) $this->projectId,
                'fromDate' => '2026-01-01',
                'toDate' => '2026-01-31',
                'subscriptionType' => SubscriptionFrequencyEnum::FREQUENCY_MONTHLY->value,
            ],
        ]);
        $this->assertResponseIsSuccessful();

        // A different date range must still resolve to the same subscription.
        $this->postCheck([
            'hour_report' => [
                'project' => (string) $this->projectId,
                'fromDate' => '2026-06-01',
                'toDate' => '2026-06-30',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame(
            'monthly',
            $this->responseJson()['frequencies']
        );
    }

    public function testCheckDropsAnEmptyVersionBeforeMatching(): void
    {
        $this->postCheck([
            'hour_report' => [
                'project' => (string) $this->projectId,
                'subscriptionType' => SubscriptionFrequencyEnum::FREQUENCY_MONTHLY->value,
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $this->postCheck(['hour_report' => ['project' => (string) $this->projectId, 'version' => '']]);

        $this->assertResponseIsSuccessful();
        $this->assertSame(
            'monthly',
            $this->responseJson()['frequencies']
        );
    }

    public function testIndexShowsTheVersionOfAVersionScopedSubscription(): void
    {
        $this->persistSubscription(
            SubscriptionFrequencyEnum::FREQUENCY_MONTHLY,
            self::EMAIL,
            ['hour_report' => ['project' => (string) $this->projectId, 'version' => (string) $this->versionId]]
        );

        $crawler = $this->client->request('GET', '/admin/subscription/');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString($this->versionName, $crawler->html());
    }

    private function submitFilter(string $value): Crawler
    {
        $crawler = $this->client->request('GET', '/admin/subscription/');
        $form = $crawler->filter('form[name="subscription_filter"]')->form();
        $form['subscription_filter[urlParams]'] = $value;

        return $this->client->submit($form);
    }

    /**
     * @param array<string, array<string, string>> $payload
     */
    private function postCheck(array $payload): void
    {
        $this->requestJson('POST', sprintf('/admin/subscription/%d/check', $this->userId), $payload);
    }

    /**
     * @param array<string, array<string, string>>|null $urlParams
     */
    private function persistSubscription(
        SubscriptionFrequencyEnum $frequency,
        string $email = self::EMAIL,
        ?array $urlParams = null,
    ): int {
        $subscription = new Subscription();
        $subscription->setEmail($email);
        $subscription->setSubject(SubscriptionSubjectEnum::HOUR_REPORT);
        $subscription->setFrequency($frequency);
        $subscription->setUrlParams($urlParams ?? ['hour_report' => ['project' => (string) $this->projectId]]);
        $this->entityManager->persist($subscription);
        $this->entityManager->flush();
        $id = $this->requireId($subscription->getId());
        $this->entityManager->clear();

        return $id;
    }
}
