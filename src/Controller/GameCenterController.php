<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Error\LoaderError;

#[Route('/gamecenter')]
class GameCenterController extends AbstractController
{
    #[Route('', name: 'game_center')]
    public function index(?string $game = null): Response
    {
        return $this->render('game_center/index.html.twig', [
            'games' => $this->getGames(),
        ]);
    }

    /**
     * The trailing slash is important to make assets work (cf. self::asset()).
     */
    #[Route('/{game}/', name: 'game_center_play')]
    public function play(string $game): Response
    {
        $games = $this->getGames();
        if (!isset($games[$game])) {
            throw new NotFoundHttpException(sprintf('%s not found.', $game));
        }

        try {
            return $this->render('game_center/'.$game.'/index.html.twig');
        } catch (LoaderError) {
            throw new NotFoundHttpException(sprintf('%s not found.', $game));
        }
    }

    #[Route('/{game}/{path}', requirements: ['path' => '.+'])]
    public function asset(string $game, string $path): Response
    {
        $assetPath = __DIR__.'/../../templates/game_center/'.$game.'/'.$path;

        if (!file_exists($assetPath)) {
            throw new NotFoundHttpException();
        }

        $ext = pathinfo($assetPath, PATHINFO_EXTENSION);
        $mimeTypes = new MimeTypes();
        $types = $mimeTypes->getMimeTypes($ext);

        return new BinaryFileResponse($assetPath, Response::HTTP_OK, [
            'content-type' => $types[0] ?? 'text/plain',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function getGames(): array
    {
        return [
            'snake' => 'Snake',
        ];
    }
}
