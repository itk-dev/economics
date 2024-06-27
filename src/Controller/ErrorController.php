<?php

namespace App\Controller;

use App\Exception\EconomicsException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ErrorController extends AbstractController
{
    public function __construct(
    ) {
    }

    public function show(\Throwable $exception): Response
    {
        $code = $exception->getCode();

        if ($exception instanceof NotFoundHttpException) {
            $code = 404;
        }

        if ($exception instanceof AccessDeniedHttpException) {
            $code = 403;
            $message = 'Access denied.';
        }

        if ($exception instanceof EconomicsException) {
            $message = $exception->getMessage();
        }

        return $this->render('error/error.html.twig', [
            'code' => $code,
            'message' => $message ?? null,
        ]);
    }
}
