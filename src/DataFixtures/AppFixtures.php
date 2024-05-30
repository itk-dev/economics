<?php

namespace App\DataFixtures;

use App\Entity\Client;
use App\Entity\DataProvider;
use App\Entity\Issue;
use App\Entity\Project;
use App\Entity\Version;
use App\Entity\Worklog;
use App\Enum\ClientTypeEnum;
use App\Service\JiraApiService;
use App\Service\LeantimeApiService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $dataProviders = [];

        $dataProvider1 = new DataProvider();
        $dataProvider1->setName('Data Provider 1 - Jira');
        $dataProvider1->setEnabled(true);
        $dataProvider1->setClass(JiraApiService::class);
        $dataProvider1->setUrl('http://localhost/');
        $dataProvider1->setSecret('Not so secret');

        $dataProviders[] = $dataProvider1;

        $dataProvider2 = new DataProvider();
        $dataProvider2->setName('Data Provider 2 - Leantime');
        $dataProvider2->setEnabled(true);
        $dataProvider2->setClass(LeantimeApiService::class);
        $dataProvider2->setUrl('http://localhost/');
        $dataProvider2->setSecret('Not so secret');

        $dataProviders[] = $dataProvider2;

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
            }

            for ($i = 0; $i < 10; ++$i) {
                $project = new Project();
                $project->setName("project-$key-$i");
                $project->setProjectTrackerId("project-$key-$i");
                $project->setProjectTrackerKey("project-$key-$i");
                $project->setProjectTrackerProjectUrl('http://localhost/');
                $project->setInclude(true);
                $project->setProjectLeadMail('test@economics.local.itkdev.dk');
                $project->setProjectLeadName('Test Testesen');
                $project->setDataProvider($dataProvider);

                $manager->persist($project);

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
                    $issue = new Issue();
                    $issue->setName("issue-$i-$j");
                    $issue->setProject($project);
                    $issue->setProjectTrackerKey("issue-$i-$j");
                    $issue->setProjectTrackerId("issue-$i-$j");
                    $issue->setAccountId('Account 1');
                    $issue->setAccountKey('Account 1');
                    $issue->setEpicName('Epic 1');
                    $issue->setEpicKey('Epic 1');
                    $issue->setStatus('Lukket');
                    $issue->setDataProvider($dataProvider);
                    $issue->addVersion($versions[$j % count($versions)]);
                    $issue->setResolutionDate(new \DateTime());
                    $issue->setPlanHours($j);
                    $issue->setHoursRemaining($j);
                    $manager->persist($issue);

                    for ($k = 0; $k < 100; ++$k) {
                        $worklog = new Worklog();
                        $worklog->setProjectTrackerIssueId("worklog-$key-$i-$j-$k");
                        $worklog->setWorklogId($i * 100000 + $j * 1000 + $k);
                        $worklog->setDescription("Beskrivelse af worklog-$key-$i-$j-$k");
                        $worklog->setIsBilled(false);
                        $worklog->setProject($project);
                        $worklog->setWorker('test@test');
                        $worklog->setTimeSpentSeconds(60 * 15 * ($k + 1));
                        $worklog->setStarted(new \DateTime());
                        $worklog->setIssue($issue);
                        $worklog->setDataProvider($dataProvider);

                        $manager->persist($worklog);
                    }

                    $manager->flush();
                }
            }

            $manager->flush();
            $manager->clear();
        }
    }
}
