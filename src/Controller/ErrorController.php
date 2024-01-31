<?php

namespace App\Controller;

use App\Exception\EconomicsException;
use App\Service\ViewService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ErrorController extends AbstractController
{
    public function __construct(
        private readonly ViewService $viewService,
    ) {
    }

    public function show(\Throwable $exception): Response
    {
        $code = $exception->getCode();

        if ($exception instanceof NotFoundHttpException) {
            $code = 404;
        }

        if ($exception instanceof EconomicsException) {
            $message = $exception->getMessage();
        }

        return $this->render('error/error.html.twig', $this->viewService->addView([
            'code' => $code,
            'message' => $message ?? null,
        ]));
    }
}
