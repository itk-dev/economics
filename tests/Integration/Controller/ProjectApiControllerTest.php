<?php

namespace App\Tests\Integration\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProjectApiControllerTest extends WebTestCase
{
    private const API_URL = '/api/projects';

    public function testRejectsMissingApiKeyHeader(): void
    {
        $client = static::createClient();
        $client->request('GET', self::API_URL);

        $this->assertResponseStatusCodeSame(401);
        $this->assertJsonErrorMessage($client, 'No API key provided');
    }

    public function testRejectsInvalidApiKey(): void
    {
        $client = static::createClient();
        $client->request('GET', self::API_URL, [], [], [
            'HTTP_X-Api-Key' => 'wrong-key',
        ]);

        $this->assertResponseStatusCodeSame(401);
        $this->assertJsonErrorMessage($client, 'Invalid API key');
    }

    public function testAcceptsValidApiKeyAndReturnsProjects(): void
    {
        $client = static::createClient();
        $client->request('GET', self::API_URL, [], [], [
            'HTTP_X-Api-Key' => 'test-api-key',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $payload = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($payload);
        $this->assertNotEmpty($payload, 'Fixtures should produce at least one project.');

        $project = $payload[0];
        $this->assertArrayHasKey('id', $project);
        $this->assertArrayHasKey('name', $project);
        $this->assertArrayHasKey('leantimeUrl', $project);
        $this->assertArrayHasKey('codeowners', $project);
        $this->assertArrayHasKey('serviceAgreement', $project);
        $this->assertIsArray($project['codeowners']);
    }

    private function assertJsonErrorMessage(KernelBrowser $client, string $expected): void
    {
        $payload = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('error', $payload);
        $this->assertSame($expected, $payload['error']);
    }
}
