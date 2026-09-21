<?php

namespace App\Tests\Integration\Controller;

class GameCenterControllerTest extends AbstractControllerTestCase
{
    public function testIndexListsTheAvailableGames(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_USER']);
        $crawler = $client->request('GET', '/gamecenter');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsStringIgnoringCase('snake', $crawler->html());
    }

    public function testKnownGameIsPlayable(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_USER']);
        $client->request('GET', '/gamecenter/snake/');

        $this->assertResponseIsSuccessful();
    }

    public function testUnknownGameIsNotFound(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_USER']);
        $client->request('GET', '/gamecenter/pong/');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testGameAssetIsServed(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_USER']);
        $asset = $this->firstGameAsset();

        $client->request('GET', '/gamecenter/snake/'.$asset);

        $this->assertResponseIsSuccessful();
    }

    public function testUnknownAssetIsNotFound(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_USER']);
        $client->request('GET', '/gamecenter/snake/does-not-exist.js');

        $this->assertResponseStatusCodeSame(404);
    }

    private function firstGameAsset(): string
    {
        $directory = dirname(__DIR__, 3).'/templates/game_center/snake';
        $assets = array_values(array_filter(
            scandir($directory) ?: [],
            fn (string $file) => is_file($directory.'/'.$file) && !str_ends_with($file, '.twig')
        ));

        $this->assertNotEmpty($assets, 'Expected the snake game to ship at least one asset.');

        return $assets[0];
    }
}
