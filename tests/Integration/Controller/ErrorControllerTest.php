<?php

namespace App\Tests\Integration\Controller;

use App\Controller\ErrorController;
use App\Exception\EconomicsException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ErrorControllerTest extends KernelTestCase
{
    private ErrorController $controller;

    protected function setUp(): void
    {
        self::bootKernel();
        $controller = self::getContainer()->get(ErrorController::class);
        \assert($controller instanceof ErrorController);
        $this->controller = $controller;
    }

    private function render(\Throwable $exception): string
    {
        $content = $this->controller->show($exception)->getContent();
        $this->assertIsString($content);

        return $content;
    }

    public function testNotFoundIsRenderedAs404(): void
    {
        $this->assertStringContainsString('404', $this->render(new NotFoundHttpException('nope')));
    }

    public function testAccessDeniedIsRenderedAs403WithAMessage(): void
    {
        $content = $this->render(new AccessDeniedHttpException());

        $this->assertStringContainsString('403', $content);
        $this->assertStringContainsString('Access denied.', $content);
    }

    public function testEconomicsExceptionMessageIsShown(): void
    {
        $content = $this->render(new EconomicsException('Something specific went wrong', 418));

        $this->assertStringContainsString('418', $content);
        $this->assertStringContainsString('Something specific went wrong', $content);
    }

    public function testGenericExceptionFallsBackToItsCode(): void
    {
        $this->assertStringContainsString('500', $this->render(new \RuntimeException('boom', 500)));
    }
}
