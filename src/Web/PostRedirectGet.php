<?php

namespace App\Web;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Post/Redirect/Get back to the page a form was posted from, with its query (search, page...).
 *
 * The form sends the route ("_return_route", the planner by default), its parameters ("_return_params",
 * e.g. "slug=digger-51293") and the query ("_return"). Any page of the application answering GET is
 * accepted: the URL is always generated from a route name, never taken from the client, and the
 * parameters must match the route requirements.
 */
final class PostRedirectGet
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private RouterInterface $router,
    ) {
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
        if (!$this->isPage($route)) {
            $route = 'app_home';
        }
        parse_str($return, $query);
        parse_str($request->request->getString('_return_params'), $parameters);
        // A route parameter is a string ("slug[]=x" would give an array).
        $parameters = array_filter($parameters, is_scalar(...));

        try {
            // The route parameters win over a query parameter of the same name.
            $url = $this->urlGenerator->generate($route, array_replace($query, $parameters));
        } catch (RoutingException|\TypeError) {
            // Missing or invalid route parameters (an array from the query, too): the planner.
            $url = $this->urlGenerator->generate('app_home');
        }

        return new RedirectResponse($url, Response::HTTP_SEE_OTHER);
    }

    /**
     * A route of the application that can be displayed (answers GET), excluding the internal ones.
     */
    private function isPage(string $route): bool
    {
        $definition = '' === $route || str_starts_with($route, '_') ? null : $this->router->getRouteCollection()->get($route);

        return null !== $definition && ([] === $definition->getMethods() || in_array('GET', $definition->getMethods(), true));
    }
}
