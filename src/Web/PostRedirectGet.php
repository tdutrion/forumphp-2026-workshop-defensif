<?php

namespace App\Web;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Post/Redirect/Get back to the page a form was posted from, with its query (search, page...).
 *
 * The form sends the route ("_return_route", the planner by default), its parameters ("_return_params",
 * e.g. "slug=digger-51293") and the query ("_return"). Only the routes listed here are accepted, and
 * their parameters must match the route requirements: never a URL chosen by the client.
 */
final class PostRedirectGet
{
    private const ROUTES = ['app_home', 'app_history', 'app_unwanted_films', 'app_settings', 'app_film_show'];

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
        parse_str($request->request->getString('_return_params'), $parameters);

        try {
            // The route parameters win over a query parameter of the same name.
            $url = $this->urlGenerator->generate($route, array_replace($query, $parameters));
        } catch (RoutingException) {
            // Missing or invalid route parameters: the planner.
            $url = $this->urlGenerator->generate('app_home');
        }

        return new RedirectResponse($url, Response::HTTP_SEE_OTHER);
    }
}
