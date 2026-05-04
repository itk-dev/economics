<?php

namespace App\Service;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class ReportRenderer
{
    public const FRAGMENT_HEADER = 'X-Report-Fragment';

    public function __construct(
        private readonly Environment $twig,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * Renders a report. When the current request carries the
     * `X-Report-Fragment` header, only the fragment template is rendered
     * (no outer layout). The fragment template's root element should carry
     * `id="report-fragment"` so the JS hide-controller can swap it cleanly.
     *
     * @param array<string, mixed> $context
     */
    public function render(string $fullTemplate, string $fragmentTemplate, array $context): Response
    {
        $request = $this->requestStack->getCurrentRequest();
        $isFragment = null !== $request && '1' === $request->headers->get(self::FRAGMENT_HEADER);

        $template = $isFragment ? $fragmentTemplate : $fullTemplate;

        // Match AbstractController::render(): auto-convert Form to FormView.
        foreach ($context as $key => $value) {
            if ($value instanceof FormInterface) {
                $context[$key] = $value->createView();
            }
        }

        return new Response($this->twig->render($template, $context));
    }
}
