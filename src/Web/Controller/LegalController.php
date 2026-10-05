<?php

namespace App\Web\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

/**
 * The legal pages a French website must show: legal notice, terms of use, privacy policy.
 * Public. Long legal texts are one template per language (legal/<page>.<locale>.html.twig),
 * English when the language of the visitor has none: a catalog of messages would need HTML.
 */
#[Route('/legal')]
class LegalController extends AbstractController
{
    public function __construct(private Environment $twig)
    {
    }

    #[Route('/notice', name: 'app_legal_notice', methods: ['GET'])]
    public function notice(Request $request): Response
    {
        return $this->renderPage('notice', $request);
    }

    #[Route('/terms', name: 'app_legal_terms', methods: ['GET'])]
    public function terms(Request $request): Response
    {
        return $this->renderPage('terms', $request);
    }

    #[Route('/privacy', name: 'app_legal_privacy', methods: ['GET'])]
    public function privacy(Request $request): Response
    {
        return $this->renderPage('privacy', $request);
    }

    private function renderPage(string $page, Request $request): Response
    {
        $template = sprintf('legal/%s.%s.html.twig', $page, $request->getLocale());
        if (!$this->twig->getLoader()->exists($template)) {
            $template = sprintf('legal/%s.en.html.twig', $page);
        }

        return $this->render($template);
    }
}
