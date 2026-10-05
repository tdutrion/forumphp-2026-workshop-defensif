<?php

namespace App\Web;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Post/Redirect/Get back to the page a form was posted from, with its query (search, page...).
 *
 * The form sends the route ("_return_route", the planner by default) and the query ("_return").
 * Only the routes listed here are accepted: never a URL chosen by the client.
 */
final class PostRedirectGet
{
    private const ROUTES = ['app_home', 'app_history', 'app_unwanted_films', 'app_settings'];

    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    /**
     * @return RedirectResponse|null null when the form did not ask to come back
     */
    public function back(Request $request): ?RedirectResponse
    {
        $route = $request->request->getString('_return_route');
        $return = $request->request->getString('_return');
        if ('' === $route && '' === $return) {
            return null;
        }
        if (!in_array($route, self::ROUTES, true)) {
            $route = 'app_home';
        }
        parse_str($return, $query);

        return new RedirectResponse($this->urlGenerator->generate($route, $query), Response::HTTP_SEE_OTHER);
    }
}
