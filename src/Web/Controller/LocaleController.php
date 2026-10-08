<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Web\EventListener\LocaleFromCookieListener;
use App\Web\PostRedirectGet;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Language switch of the top menu, for everyone: the choice is kept in a cookie for a year, then
 * Post/Redirect/Get back to the page. No CSRF token: the language has no sensitive effect, and a
 * token would open a session for every visitor.
 */
class LocaleController extends AbstractController
{
    public function __construct(
        private PostRedirectGet $postRedirectGet,
        #[Autowire('%kernel.enabled_locales%')]
        private array $locales,
    ) {
    }

    #[Route('/locale', name: 'app_locale', methods: ['POST'])]
    public function switch(Request $request): Response
    {
        $locale = $request->request->getString('locale');
        if (!in_array($locale, $this->locales, true)) {
            throw new BadRequestHttpException('error.locale_invalid');
        }

        $response = $this->postRedirectGet->back($request) ?? $this->redirectToRoute('app_home', [], Response::HTTP_SEE_OTHER);
        $response->headers->setCookie(Cookie::create(LocaleFromCookieListener::COOKIE, $locale, new \DateTimeImmutable('+1 year'), sameSite: Cookie::SAMESITE_LAX));

        return $response;
    }
}
