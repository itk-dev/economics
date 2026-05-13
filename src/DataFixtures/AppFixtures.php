<?php

namespace App\DataFixtures;

use App\Entity\Account;
use App\Entity\Client;
use App\Entity\CybersecurityAgreement;
use App\Entity\DataProvider;
use App\Entity\Epic;
use App\Entity\Invoice;
use App\Entity\InvoiceEntry;
use App\Entity\Issue;
use App\Entity\Product;
use App\Entity\Project;
use App\Entity\ProjectBilling;
use App\Entity\ServiceAgreement;
use App\Entity\Subscription;
use App\Entity\Version;
use App\Entity\Worker;
use App\Entity\WorkerGroup;
use App\Entity\Worklog;
use App\Enum\BillableKindsEnum;
use App\Enum\ClientTypeEnum;
use App\Enum\HostingProviderEnum;
use App\Enum\InvoiceEntryTypeEnum;
use App\Enum\IssueStatusEnum;
use App\Enum\SubscriptionFrequencyEnum;
use App\Enum\SubscriptionSubjectEnum;
use App\Enum\SystemOwnerNoticeEnum;
use App\Service\LeantimeApiService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Strip the DBAL logging + profiler middlewares: in dev they buffer every
        // executed statement in memory, which dominates wall-clock time and memory
        // when inserting ~20k worklogs.
        if ($manager instanceof EntityManagerInterface) {
            $configuration = $manager->getConnection()->getConfiguration();
            $configuration->setMiddlewares(array_filter(
                $configuration->getMiddlewares(),
                static fn ($middleware) => !($middleware instanceof \Doctrine\DBAL\Logging\Middleware)
                    && !($middleware instanceof \Doctrine\Bundle\DoctrineBundle\Middleware\DebugMiddleware),
            ));
        }

        $year = (new \DateTime())->format('Y');

        // Precompute the 100 distinct "started" timestamps the worklog loop uses
        // instead of re-parsing them 20,000 times.
        $startedByK = [];
        for ($k = 0; $k < 100; ++$k) {
            $modMonth = str_pad((string) ($k % 12 + 1), 2, '0', STR_PAD_LEFT);
            $modDay = str_pad((string) ($k % 28 + 1), 2, '0', STR_PAD_LEFT);
            $startedByK[$k] = \DateTime::createFromFormat('U', (string) strtotime("$year-$modMonth-$modDay"), new \DateTimeZone('Europe/Copenhagen'));
        }

        $dataProviders = [];

        $dataProvider1 = new DataProvider();
        $dataProvider1->setName('Data Provider 1 - Leantime 1');
        $dataProvider1->setEnabled(true);
        $dataProvider1->setClass(LeantimeApiService::class);
        $dataProvider1->setUrl('http://localhost/');
        $dataProvider1->setSecret('Not so secret');

        $dataProviders[] = $dataProvider1;

        $dataProvider2 = new DataProvider();
        $dataProvider2->setName('Data Provider 2 - Leantime 2');
        $dataProvider2->setEnabled(true);
        $dataProvider2->setClass(LeantimeApiService::class);
        $dataProvider2->setUrl('http://localhost/');
        $dataProvider2->setSecret('Not so secret');

        $dataProviders[] = $dataProvider2;

        $workerArray = [];
        $workerEntities = [];

        for ($i = 0; $i < 10; ++$i) {
            $worker = new Worker();
            $worker->setEmail('test'.$i.'@test');
            $worker->setWorkload(37);
            $manager->persist($worker);
            $workerArray[] = 'test'.$i.'@test';
            $workerEntities[] = $worker;
        }

        $epic = new Epic();
        $epic->setTitle('Epic 1');
        $manager->persist($epic);

        // Stash references to entities the tail block needs, so we can avoid
        // re-querying them after a clear().
        $projectsByKey = [];
        $clientsByKey = [];

        $batchSize = 500;
        $persisted = 0;

        foreach ($dataProviders as $key => $dataProvider) {
            $manager->persist($dataProvider);

            for ($c = 0; $c < 2; ++$c) {
                $client = new Client();
                $client->setName("client $key-$c");
                $client->setDataProvider($dataProvider);
                $client->setProjectTrackerId("client $key-$c");
                $client->setEan('EAN123456789');
                $client->setPsp('PSP123456789');
                $client->setContact("Kontakt Kontaktesen $key");
                $client->setStandardPrice(500);
                $client->setType(0 == $c ? ClientTypeEnum::INTERNAL : ClientTypeEnum::EXTERNAL);
                $client->setCustomerKey("Customer Key $key-$c");
                $clientVersionName = "PB-$key-$c";
                $client->setVersionName($clientVersionName);

                $manager->persist($client);
                $clientsByKey["$key-$c"] = $client;
            }

            for ($i = 0; $i < 10; ++$i) {
                $modBillable = 0 == $i % 2 ? true : false;

                $project = new Project();
                $project->setName("project-$key-$i");
                $project->setProjectTrackerId("project-$key-$i");
                $project->setProjectTrackerKey("project-$key-$i");
                $project->setProjectTrackerProjectUrl('http://localhost/');
                $project->setInclude(true);
                $project->setProjectLeadMail('test@economics.local.itkdev.dk');
                $project->setProjectLeadName('Test Testesen');
                $project->setDataProvider($dataProvider);
                $project->setIsBillable($modBillable);

                $manager->persist($project);
                $projectsByKey["$key-$i"] = $project;

                $versions = [];
                for ($v = 0; $v < 4; ++$v) {
                    $version = new Version();
                    if ($v < 2) {
                        $version->setName("PB-$key-$v");
                    } else {
                        $version->setName("version $key-$i-$v");
                    }
                    $version->setProject($project);
                    $version->setDataProvider($dataProvider);
                    $version->setProjectTrackerId("version $key-$i-$v");

                    $manager->persist($version);

                    $versions[] = $version;
                }

                for ($j = 0; $j < 10; ++$j) {
                    $modStatus = 0 == $i % 2 ? IssueStatusEnum::DONE : IssueStatusEnum::NEW;
                    $issue = new Issue();
                    $issue->setName("issue-$i-$j");
                    $issue->setProject($project);
                    $issue->setProjectTrackerKey("issue-$i-$j");
                    $issue->setProjectTrackerId("issue-$i-$j");
                    $issue->setAccountId('Account 1');
                    $issue->setAccountKey('Account 1');
                    $issue->setStatus($modStatus);
                    $issue->setDataProvider($dataProvider);
                    $issue->addVersion($versions[$j % count($versions)]);
                    $issue->setResolutionDate(new \DateTime());
                    $issue->setPlanHours($j);
                    $issue->setHoursRemaining($j);
                    $issue->setWorker($workerArray[rand(0, 9)]);
                    $issue->setDueDate(new \DateTime());
                    $issue->setWorker($workerArray[rand(0, 9)]);
                    $issue->setLinkToIssue('www.example.com');
                    $manager->persist($issue);

                    if (0 == $key && 0 == $i && 0 == $j) {
                        $issue->addEpic($epic);
                    }

                    $modKind = 0 == $i % 2 ? BillableKindsEnum::GENERAL_BILLABLE : null;
                    $issueWorker = $workerArray[$i % 10];

                    for ($k = 0; $k < 100; ++$k) {
                        $worklog = new Worklog();
                        $worklog->setProjectTrackerIssueId("worklog-$key-$i-$j-$k");
                        $worklog->setWorklogId($i * 100000 + $j * 1000 + $k);
                        $worklog->setDescription("Beskrivelse af worklog-$key-$i-$j-$k");
                        $worklog->setIsBilled(false);
                        $worklog->setProject($project);
                        $worklog->setWorker($issueWorker);
                        $worklog->setTimeSpentSeconds(60 * 15 * ($k + 1));
                        $worklog->setStarted(clone $startedByK[$k]);
                        $worklog->setIssue($issue);
                        $worklog->setDataProvider($dataProvider);
                        $worklog->setKind($modKind);
                        $manager->persist($worklog);

                        if (0 === ++$persisted % $batchSize) {
                            $manager->flush();
                            // Do not clear() — later iterations and the tail
                            // block still reference these managed entities.
                        }
                    }
                }
            }
        }

        $manager->flush();

        $worker0 = $workerEntities[0];
        $worker1 = $workerEntities[1];
        $project00 = $projectsByKey['0-0'];
        $project01 = $projectsByKey['0-1'];
        $project02 = $projectsByKey['0-2'];
        $client00 = $clientsByKey['0-0'];
        $client01 = $clientsByKey['0-1'];

        // Accounts
        $account1 = new Account();
        $account1->setName('Test Account 1');
        $account1->setValue('ACC001');
        $manager->persist($account1);

        $account2 = new Account();
        $account2->setName('Test Account 2');
        $account2->setValue('ACC002');
        $manager->persist($account2);

        // Worker Groups
        $groupAlpha = new WorkerGroup();
        $groupAlpha->setName('Group Alpha');
        for ($i = 0; $i < 5; ++$i) {
            $groupAlpha->addWorker($workerEntities[$i]);
        }
        $manager->persist($groupAlpha);

        $groupBeta = new WorkerGroup();
        $groupBeta->setName('Group Beta');
        for ($i = 5; $i < 10; ++$i) {
            $groupBeta->addWorker($workerEntities[$i]);
        }
        $manager->persist($groupBeta);

        // Products
        $product1 = new Product();
        $product1->setName('Product Alpha');
        $product1->setProject($project00);
        $product1->setPrice('100.00');
        $manager->persist($product1);

        $product2 = new Product();
        $product2->setName('Product Beta');
        $product2->setProject($project00);
        $product2->setPrice('200.00');
        $manager->persist($product2);

        $product3 = new Product();
        $product3->setName('Product Gamma');
        $product3->setProject($project01);
        $product3->setPrice('150.00');
        $manager->persist($product3);

        // Project Billings
        $billing1 = new ProjectBilling();
        $billing1->setName('Billing Q1');
        $billing1->setProject($project00);
        $billing1->setRecorded(true);
        $billing1->setPeriodStart(new \DateTime("$year-01-01"));
        $billing1->setPeriodEnd(new \DateTime("$year-12-31"));
        $manager->persist($billing1);

        $billing2 = new ProjectBilling();
        $billing2->setName('Billing Q2');
        $billing2->setProject($project00);
        $billing2->setRecorded(false);
        $billing2->setPeriodStart(new \DateTime("$year-01-01"));
        $billing2->setPeriodEnd(new \DateTime("$year-12-31"));
        $manager->persist($billing2);

        // Invoices
        $invoice1 = new Invoice();
        $invoice1->setName('Invoice Alpha');
        $invoice1->setProject($project00);
        $invoice1->setClient($client00);
        $invoice1->setRecorded(true);
        $invoice1->setRecordedDate(new \DateTime('-2 months'));
        $invoice1->setNoCost(false);
        $manager->persist($invoice1);

        $invoice2 = new Invoice();
        $invoice2->setName('Invoice Beta');
        $invoice2->setProject($project00);
        $invoice2->setClient($client01);
        $invoice2->setRecorded(false);
        $invoice2->setNoCost(false);
        $manager->persist($invoice2);

        $invoice3 = new Invoice();
        $invoice3->setName('Invoice Gamma');
        $invoice3->setProject($project01);
        $invoice3->setClient($client00);
        $invoice3->setRecorded(true);
        $invoice3->setRecordedDate(new \DateTime('-1 month'));
        $invoice3->setNoCost(true);
        $manager->persist($invoice3);

        $invoice4 = new Invoice();
        $invoice4->setName('Invoice Delta');
        $invoice4->setProject($project00);
        $invoice4->setClient($client00);
        $invoice4->setRecorded(false);
        $invoice4->setProjectBilling($billing2);
        $invoice4->setNoCost(false);
        $manager->persist($invoice4);

        // Invoice Entries
        $invoiceEntry1 = new InvoiceEntry();
        $invoiceEntry1->setEntryType(InvoiceEntryTypeEnum::WORKLOG);
        $invoice1->addInvoiceEntry($invoiceEntry1);
        $manager->persist($invoiceEntry1);

        $invoiceEntry2 = new InvoiceEntry();
        $invoiceEntry2->setEntryType(InvoiceEntryTypeEnum::MANUAL);
        $invoice2->addInvoiceEntry($invoiceEntry2);
        $manager->persist($invoiceEntry2);

        // Attach some worklogs to invoice entry and mark some as billed
        $worklogRepo = $manager->getRepository(Worklog::class);
        $worklogsToAttach = $worklogRepo->findBy(['project' => $project00], ['id' => 'ASC'], 5);
        foreach ($worklogsToAttach as $wl) {
            $wl->setInvoiceEntry($invoiceEntry1);
        }

        $worklogsToBill = $worklogRepo->findBy(['project' => $project00], ['id' => 'ASC'], 10, 10);
        foreach ($worklogsToBill as $wl) {
            $wl->setIsBilled(true);
        }

        // Service Agreements
        $sa1 = new ServiceAgreement();
        $sa1->setProject($project00);
        $sa1->setClient($client00);
        $sa1->setHostingProvider(HostingProviderEnum::ADM);
        $sa1->setProjectLead($worker0);
        $sa1->setPrice(1000.0);
        $sa1->setValidFrom(new \DateTime("$year-01-01"));
        $sa1->setValidTo(new \DateTime("$year-12-31"));
        $sa1->setIsActive(true);
        $sa1->setSystemOwnerNotices([SystemOwnerNoticeEnum::SERVERFLYTNING]);
        $manager->persist($sa1);

        $sa2 = new ServiceAgreement();
        $sa2->setProject($project01);
        $sa2->setClient($client01);
        $sa2->setHostingProvider(HostingProviderEnum::HETZNER);
        $sa2->setProjectLead($worker1);
        $sa2->setPrice(2000.0);
        $sa2->setValidFrom(new \DateTime("$year-01-01"));
        $sa2->setValidTo(new \DateTime("$year-12-31"));
        $sa2->setIsActive(false);
        $sa2->setSystemOwnerNotices([SystemOwnerNoticeEnum::SIKKERHEDSPATCH]);
        $manager->persist($sa2);

        $sa3 = new ServiceAgreement();
        $sa3->setProject($project02);
        $sa3->setClient($client00);
        $sa3->setHostingProvider(HostingProviderEnum::DMZ);
        $sa3->setProjectLead($worker0);
        $sa3->setPrice(1500.0);
        $sa3->setValidFrom(new \DateTime("$year-01-01"));
        $sa3->setValidTo(new \DateTime("$year-12-31"));
        $sa3->setIsActive(true);
        $sa3->setSystemOwnerNotices([SystemOwnerNoticeEnum::CYBERSIKKERSHEDSOPDATERING]);
        $manager->persist($sa3);

        // Cybersecurity Agreement
        $ca1 = new CybersecurityAgreement();
        $ca1->setServiceAgreement($sa1);
        $ca1->setQuarterlyHours(10.0);
        $ca1->setNote('Test cybersecurity note');
        $manager->persist($ca1);

        $sa1->setCybersecurityAgreement($ca1);

        // Subscriptions
        $sub1 = new Subscription();
        $sub1->setEmail('subscriber@test.com');
        $sub1->setSubject(SubscriptionSubjectEnum::HOUR_REPORT);
        $sub1->setFrequency(SubscriptionFrequencyEnum::FREQUENCY_MONTHLY);
        $sub1->setUrlParams(['param1' => 'value1']);
        $manager->persist($sub1);

        $sub2 = new Subscription();
        $sub2->setEmail('subscriber@test.com');
        $sub2->setSubject(SubscriptionSubjectEnum::HOUR_REPORT);
        $sub2->setFrequency(SubscriptionFrequencyEnum::FREQUENCY_QUARTERLY);
        $sub2->setUrlParams(['param1' => 'value1']);
        $manager->persist($sub2);

        $sub3 = new Subscription();
        $sub3->setEmail('other@test.com');
        $sub3->setSubject(SubscriptionSubjectEnum::HOUR_REPORT);
        $sub3->setFrequency(SubscriptionFrequencyEnum::FREQUENCY_MONTHLY);
        $sub3->setUrlParams(['param2' => 'value2']);
        $manager->persist($sub3);

        $manager->flush();
    }
}
